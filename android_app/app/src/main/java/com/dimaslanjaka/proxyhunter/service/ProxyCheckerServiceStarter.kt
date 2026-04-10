package com.dimaslanjaka.proxyhunter.service

import android.content.Context
import android.content.Intent
import android.os.Build
import com.dimaslanjaka.proxyhunter.data.ProxyItem
import com.dimaslanjaka.proxyhunter.data.ProxyManager

data class ProxyCheckerServiceOptions(
  val useProxyCheckService: Boolean,
  val useTun2SocksService: Boolean,
  val useCheckerHttpService: Boolean
)

class ProxyCheckerServiceStarter(context: Context) {
  private val appContext = context.applicationContext

  fun stopAll() {
    appContext.stopService(Intent(appContext, ProxyCheckService::class.java))
    appContext.stopService(Intent(appContext, Tun2socksCompatibilityTestService::class.java))
    appContext.stopService(Intent(appContext, CheckerHttpService::class.java))
    ProxyManager.stopAllServices()
  }

  fun start(
    options: ProxyCheckerServiceOptions,
    proxies: List<ProxyItem>? = null,
    isPriority: Boolean = false
  ) {
    if (proxies != null) {
      ProxyManager.set(proxies)
    }

    val checkerHttpProxies = (proxies ?: ProxyManager.get()).map { it.toString() }

    if (options.useProxyCheckService) {
      val serviceIntent = Intent(appContext, ProxyCheckService::class.java).apply {
        if (isPriority) putExtra(ProxyCheckService.EXTRA_PRIORITY, true)
      }
      startServiceCompat(serviceIntent)
    }

    if (options.useTun2SocksService) {
      val serviceIntent = Intent(appContext, Tun2socksCompatibilityTestService::class.java).apply {
        if (isPriority) putExtra(Tun2socksCompatibilityTestService.EXTRA_PRIORITY, true)
      }
      startServiceCompat(serviceIntent)
    }

    if (options.useCheckerHttpService && checkerHttpProxies.isNotEmpty()) {
      val serviceIntent = Intent(appContext, CheckerHttpService::class.java).apply {
        putStringArrayListExtra("proxies", ArrayList(checkerHttpProxies))
      }
      startServiceCompat(serviceIntent)
    }
  }

  private fun startServiceCompat(intent: Intent) {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
      appContext.startForegroundService(intent)
    } else {
      appContext.startService(intent)
    }
  }
}

