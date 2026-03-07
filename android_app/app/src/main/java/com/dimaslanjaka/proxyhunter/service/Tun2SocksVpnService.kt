package com.dimaslanjaka.proxyhunter.service

import android.content.Intent
import android.net.VpnService
import android.os.ParcelFileDescriptor
import com.dimaslanjaka.prefs.LocalSharedPrefs
import engine.Engine
import engine.Key
import timber.log.Timber
import java.io.IOException
import java.net.InetAddress
import java.net.UnknownHostException
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * A VPN Service that uses the tun2socks engine to route traffic through a SOCKS5 proxy.
 */
class Tun2SocksVpnService : VpnService() {

    private var executor: ExecutorService? = null
    private var tunDescriptor: ParcelFileDescriptor? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        Timber.tag(TAG).i("VPN Service starting...")
        startVpn()
        return START_STICKY
    }

    private fun startVpn() {
        if (tunDescriptor != null) {
            Timber.tag(TAG).d("VPN is already running, skipping start")
            return
        }

        try {
            val proxyUrl = getSavedProxy()
            Timber.tag(TAG).i("Establishing VPN with proxy: %s", proxyUrl)

            val builder = Builder()
                .addAddress(ADDRESS, 24)
                .addDnsServer(DNS)
                .addDisallowedApplication(packageName)
                .setSession(TAG)

            // Route all traffic except DNS to allow DNS queries to bypass VPN if SOCKS server doesn't support UDP
            addRoutesExcept(builder, DNS, 32)

            tunDescriptor = builder.establish()
            if (tunDescriptor == null) {
                Timber.tag(TAG).e("Failed to establish VPN: Builder.establish() returned null")
                stopSelf()
                return
            }

            val key = Key().apply {
                mark = 0
                mtu = 0
                device = "fd://${tunDescriptor!!.fd}"
                setInterface("")
                logLevel = "debug"
                proxy = proxyUrl
                restAPI = ""
                tcpSendBufferSize = ""
                tcpReceiveBufferSize = ""
                tcpModerateReceiveBuffer = false
            }

            Engine.insert(key)

            executor = Executors.newSingleThreadExecutor()
            executor?.submit {
                try {
                    Timber.tag(TAG).i("Starting tun2socks engine...")
                    Engine.start()
                    Timber.tag(TAG).i("Engine execution finished")
                } catch (e: Exception) {
                    Timber.tag(TAG).e(e, "Engine execution failed")
                }
            }

        } catch (e: Exception) {
            Timber.tag(TAG).e(e, "Unexpected error during VPN startup")
            cleanup()
            stopSelf()
        }
    }

    private fun getSavedProxy(): String {
        return try {
            val pref = LocalSharedPrefs.initialize(applicationContext, "proxy")
            pref.getString("socks", DEFAULT_SOCKS5) ?: DEFAULT_SOCKS5
        } catch (e: Exception) {
            Timber.tag(TAG).w(e, "Could not load saved proxy, falling back to default")
            DEFAULT_SOCKS5
        }
    }

    private fun cleanup() {
        Timber.tag(TAG).i("Cleaning up VPN resources")
        try {
            // Engine.stop() // Uncomment if the engine library provides a stop method
            tunDescriptor?.close()
        } catch (e: IOException) {
            Timber.tag(TAG).e(e, "Error closing TUN descriptor")
        } finally {
            tunDescriptor = null
            executor?.shutdownNow()
            executor = null
        }
    }

    override fun onDestroy() {
        cleanup()
        super.onDestroy()
    }

    /**
     * Configures the VPN to route all traffic EXCEPT for the specified address.
     * Useful for allowing DNS traffic to bypass the VPN.
     * @see <a href="https://stackoverflow.com/a/41289228">StackOverflow Source</a>
     */
    private fun addRoutesExcept(builder: Builder, address: String, prefixLength: Int) {
        try {
            val bytes = InetAddress.getByName(address).address
            for (i in 0 until prefixLength) {
                val res = ByteArray(bytes.size)
                // Copy prefix bits
                for (j in 0..i) {
                    val byteIdx = j / 8
                    val bitOffset = 7 - (j % 8)
                    res[byteIdx] = (res[byteIdx].toInt() or (bytes[byteIdx].toInt() and (1 shl bitOffset))).toByte()
                }
                // Flip the current bit to create the excluded route
                val currentByteIdx = i / 8
                val currentBitOffset = 7 - (i % 8)
                res[currentByteIdx] = (res[currentByteIdx].toInt() xor (1 shl currentBitOffset)).toByte()

                builder.addRoute(InetAddress.getByAddress(res), i + 1)
            }
        } catch (e: UnknownHostException) {
            Timber.tag(TAG).e(e, "Invalid host for exception route: %s", address)
        } catch (e: Exception) {
            Timber.tag(TAG).e(e, "Failed to add exception routes for %s", address)
        }
    }

    companion object {
        private const val TAG = "Tun2SocksVpn"
        private const val ADDRESS = "10.0.0.2"
        private const val DNS = "1.1.1.1"
        private const val DEFAULT_SOCKS5 = "socks5://10.88.111.24:8080"
    }
}
