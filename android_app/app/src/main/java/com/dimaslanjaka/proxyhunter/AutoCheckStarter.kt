package com.dimaslanjaka.proxyhunter

import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.content.ContextCompat
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import timber.log.Timber

object AutoCheckStarter {

  fun maybeStart(context: Context, reason: String) {
    val autoCheckEnabled = ProxyManager.prefs.getBoolean("auto_check_proxies", false)
    if (!autoCheckEnabled) return

    if (ProxyManager.isRunningFlow.value) {
      Timber.d("Auto-check already running, skipping start from %s", reason)
      return
    }

    Timber.d("Auto-check enabled, starting ProxyCheckService from %s", reason)
    val intent = Intent(context.applicationContext, ProxyCheckService::class.java)
    try {
      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
        ContextCompat.startForegroundService(context.applicationContext, intent)
      } else {
        context.applicationContext.startService(intent)
      }
    } catch (e: Exception) {
      Timber.e(e, "Failed to auto-start ProxyCheckService from %s", reason)
    }
  }
}

