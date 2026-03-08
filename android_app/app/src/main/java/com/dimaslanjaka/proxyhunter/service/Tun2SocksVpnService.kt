package com.dimaslanjaka.proxyhunter.service

import android.content.Intent
import android.content.pm.PackageManager
import android.net.VpnService
import android.os.Build
import android.os.ParcelFileDescriptor
import com.dimaslanjaka.prefs.LocalSharedPrefs
import engine.Engine
import engine.Key
import timber.log.Timber
import java.io.IOException
import java.net.InetAddress
import java.net.UnknownHostException
import java.util.concurrent.Executors

class Tun2SocksVpnService : VpnService() {
  private val executors = Executors.newFixedThreadPool(1)
  private var tun: ParcelFileDescriptor? = null

  override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
    if (intent?.action == ACTION_STOP) {
      Timber.tag(TAG).i("Received ACTION_STOP")
      stopService()
      return START_NOT_STICKY
    }
    return super.onStartCommand(intent, flags, startId)
  }

  private fun stopService() {
    try {
      tun?.close()
      Timber.tag(TAG).i("TUN descriptor closed")
    } catch (e: IOException) {
      Timber.tag(TAG).e(e, "Error closing TUN descriptor")
    } finally {
      tun = null
    }

    try {
      executors?.shutdownNow()
    } catch (e: Exception) {
      Timber.tag(TAG).e(e, "Error shutting down executor")
    }

    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
      stopForeground(STOP_FOREGROUND_REMOVE)
    } else {
      @Suppress("DEPRECATION")
      stopForeground(true)
    }
    stopSelf()
  }

  override fun onCreate() {
    super.onCreate()
    if (tun == null) {
      tun = try {
        val builder: Builder = Builder().addAddress(ADDRESS, 24)
          .addRoute(ROUTE, 0)
          .setMtu(MTU)
          .addDnsServer(DNS).addDisallowedApplication(this.application.packageName)
          .addDisallowedApplication("com.netease.yysls")
          .setSession(TAG)

        // let DNS queries bypass VPN if SOCKS server does not support UDP bind
        addRoutesExcept(builder, DNS, 32)
        builder.establish()
      } catch (e: PackageManager.NameNotFoundException) {
        throw RuntimeException(e)
      }
    }
    if (tun != null) {
      val key = Key()
      key.mark = 0
      key.mtu = 0
      key.device = "fd://" + tun!!.fd
      key.setInterface("")
      key.logLevel = "debug"
      val proxy = savedProxy
      Timber.tag(TAG).d("using proxy %s", proxy)
      key.proxy = proxy
      key.restAPI = ""
      key.tcpSendBufferSize = ""
      key.tcpReceiveBufferSize = ""
      key.tcpModerateReceiveBuffer = false
      Engine.insert(key)
      executors!!.submit { Engine.start() }
    }
  }

  private val savedProxy: String?
    get() {
      val pref = LocalSharedPrefs.initialize(applicationContext, "proxy")
      return pref.getString("socks", SOCKS5)
    }

  override fun onDestroy() {
    super.onDestroy()
    executors?.shutdownNow()
    tun?.close()
    tun = null
  }

  /**
   * Computes the inverted subnet, routing all traffic except to the specified subnet. Use prefixLength
   * of 32 or 128 for a single address.
   *
   * @see [](https://stackoverflow.com/a/41289228)
   */
  private fun addRoutesExcept(builder: Builder, address: String, prefixLength: Int) {
    try {
      val bytes = InetAddress.getByName(address).address
      for (i in 0 until prefixLength) { // each entry
        val res = ByteArray(bytes.size)
        for (j in 0..i) { // each prefix bit
          res[j / 8] = (res[j / 8].toInt() or (bytes[j / 8].toInt() and (1 shl 7 - j % 8))).toByte()
        }
        res[i / 8] = (res[i / 8].toInt() xor (1 shl 7 - i % 8)).toByte()
        builder.addRoute(InetAddress.getByAddress(res), i + 1)
      }
    } catch (e: UnknownHostException) {
      throw RuntimeException(e)
    }
  }

  companion object {
    const val ACTION_STOP = "com.dimaslanjaka.proxyhunter.STOP"
    private const val TAG = "Tun2SocksVpnService"
    private const val ETAG = "ServiceException"
    private const val ADDRESS = "10.0.0.2"
    private const val ROUTE = "0.0.0.0"
    private const val DNS = "1.1.1.1"
    private const val MTU = 1500
    private const val SOCKS5 = "socks5://10.88.111.24:8080" // Your SOCKS5 server
    // You could spin up a local SOCK5 server on your workstation with:
    // ssh -ND "*:8080" -q -C -N <username>@<remote-host>
  }
}
