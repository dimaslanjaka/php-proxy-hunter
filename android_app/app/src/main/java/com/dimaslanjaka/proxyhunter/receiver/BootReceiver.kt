package com.dimaslanjaka.proxyhunter.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import timber.log.Timber

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED || intent.action == "android.intent.action.QUICKBOOT_POWERON") {
            ProxyManager.initialize(context)
            val autoCheckEnabled = ProxyManager.prefs.getBoolean("auto_check_proxies", false)

            if (autoCheckEnabled) {
                Timber.d("Boot completed, starting ProxyCheckService")
                val serviceIntent = Intent(context, ProxyCheckService::class.java)
                try {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        context.startForegroundService(serviceIntent)
                    } else {
                        context.startService(serviceIntent)
                    }
                } catch (e: Exception) {
                    Timber.e(e, "Failed to start ProxyCheckService on boot")
                }
            }
        }
    }
}
