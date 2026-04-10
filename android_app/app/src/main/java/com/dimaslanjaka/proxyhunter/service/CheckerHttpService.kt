package com.dimaslanjaka.proxyhunter.service

import android.app.*
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import com.dimaslanjaka.proxyhunter.checker.CheckerHttp
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import kotlinx.coroutines.*

class CheckerHttpService : Service() {

  private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())
  private val checker = CheckerHttp()

  private var total = 0
  private var checked = 0
  private val SERVICE_NAME = "CheckerHttpService"

  override fun onCreate() {
    super.onCreate()
    // Call startForeground immediately in onCreate to satisfy Android 12+ requirements
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
      startForeground(
        1,
        createNotification("Initializing..."),
        ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE
      )
    } else {
      startForeground(1, createNotification("Initializing..."))
    }

    ProxyManager.initialize(this)
    ProxyManager.setRunning(SERVICE_NAME, true)
  }

  override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
    val proxies = intent?.getStringArrayListExtra("proxies") ?: arrayListOf()

    total = proxies.size
    checked = 0

    updateNotification("Starting scan...")

    ProxyManager.setRunning(SERVICE_NAME, true)

    scope.launch {
      runScan(proxies)
    }

    return START_NOT_STICKY
  }

  override fun onBind(intent: Intent?): IBinder? = null

  override fun onDestroy() {
    ProxyManager.setRunning(SERVICE_NAME, false)
    ProxyManager.setCheckingProxy(null, SERVICE_NAME)
    super.onDestroy()
    scope.cancel()
  }

  /**
   * Run scan using Flow (real-time)
   */
  private suspend fun runScan(proxies: List<String>) {
    try {
      checker.scanProxiesFlow(
        proxies,
        concurrency = 2,
        onStart = { proxy ->
          ProxyManager.setCheckingProxy(proxy, SERVICE_NAME)
        }
      ).collect { result ->
        checked++

        // 📡 Send result to UI
        ProxyManager.addResult(result)

        // Remove from checking list
        ProxyManager.clearCheckingProxy(result.proxy, SERVICE_NAME)

        // 🔔 Update notification
        updateNotification("Checked $checked / $total")

        // 📡 Send result via legacy broadcast if needed
        sendResult(result)
      }

    } catch (e: Exception) {
      updateNotification("Error: ${e.message}")
    } finally {
      updateNotification("Finished: $checked / $total")
      stopSelf()
    }
  }

  /**
   * Send result via broadcast
   */
  private fun sendResult(result: CheckerHttp.Result) {
    val intent = Intent(ACTION_RESULT).apply {
      putExtra("proxy", result.proxy)
      putExtra("tcpOk", result.tcpOk)
      putExtra("httpOk", result.httpOk)
      putExtra("httpsOk", result.httpsOk)
      putExtra("latency", result.latencyMs)
      putExtra("error", result.error)
    }

    sendBroadcast(intent)
  }

  /**
   * Notification builder
   */
  private fun createNotification(text: String): Notification {
    val channelId = CHANNEL_ID

    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
      val channel = NotificationChannel(
        channelId,
        "HTTP Proxy Checker",
        NotificationManager.IMPORTANCE_LOW
      )
      getSystemService(NotificationManager::class.java)
        .createNotificationChannel(channel)
    }

    return NotificationCompat.Builder(this, channelId)
      .setContentTitle("Proxy Checker Running")
      .setContentText(text)
      .setSmallIcon(android.R.drawable.stat_sys_download)
      .setOnlyAlertOnce(true)
      .build()
  }

  /**
   * Update notification progress
   */
  private fun updateNotification(text: String) {
    val manager = getSystemService(NotificationManager::class.java)
    manager.notify(1, createNotification(text))
  }

  companion object {
    const val CHANNEL_ID = "proxy_checker_http_channel"
    const val ACTION_RESULT = "com.dimaslanjaka.proxyhunter.RESULT"
  }
}
