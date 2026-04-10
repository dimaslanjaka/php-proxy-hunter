package com.dimaslanjaka.proxyhunter

import android.app.Application
import com.dimaslanjaka.proxyhunter.data.ProxyManager
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
    }
}
