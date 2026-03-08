package com.dimaslanjaka.proxyhunter

import android.app.Application
import android.content.Intent
import android.os.Build
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import com.google.firebase.database.FirebaseDatabase
import timber.log.Timber

class ProxyHunterApp : Application() {

    override fun onCreate() {
        super.onCreate()
        Timber.plant(Timber.DebugTree())

        // Initialize Firebase Realtime Database persistence
        try {
            FirebaseDatabase.getInstance().setPersistenceEnabled(true)
        } catch (e: Exception) {
            Timber.e(e, "Failed to enable Firebase persistence")
        }

        ProxyManager.initialize(this)
        val autoCheckEnabled = ProxyManager.prefs.getBoolean("auto_check_proxies", false)

        if (autoCheckEnabled) {
            Timber.d("Auto-check enabled, starting ProxyCheckService")
            val intent = Intent(this, ProxyCheckService::class.java)
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    startForegroundService(intent)
                } else {
                    startService(intent)
                }
            } catch (e: Exception) {
                Timber.e(e, "Failed to auto-start ProxyCheckService from Application")
            }
        }
    }
}
