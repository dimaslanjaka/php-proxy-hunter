package com.dimaslanjaka.proxyhunter.service

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import com.dimaslanjaka.proxyhunter.data.ProxyItem
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import java.util.concurrent.Executors

class ProxyCheckService : Service() {

    private val executor = Executors.newFixedThreadPool(30)
    private var total = 0
    private var checked = 0

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        startForeground(1, createNotification("Starting proxy check..."))

        Thread { startChecking() }.start()

        return START_NOT_STICKY
    }

    private fun startChecking() {
        val proxies = ProxyManager.get()
        total = proxies.size

        for (proxy in proxies) {
            executor.execute {
                ProxyChecker.check(proxy)
                synchronized(this) {
                    checked++
                }
                updateNotification()
            }
        }
    }

    private fun createNotification(text: String): Notification {
        val channelId = "proxy_checker"

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "Proxy Checker",
                NotificationManager.IMPORTANCE_LOW
            )
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }

        return NotificationCompat.Builder(this, channelId)
            .setContentTitle("Proxy Checker")
            .setContentText(text)
            .setSmallIcon(android.R.drawable.stat_sys_download)
            .build()
    }

    private fun updateNotification() {
        val text = "$checked / $total proxies checked"
        val notification = createNotification(text)
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        manager.notify(1, notification)
    }

    override fun onBind(intent: Intent?): IBinder? {
        return null
    }
}
