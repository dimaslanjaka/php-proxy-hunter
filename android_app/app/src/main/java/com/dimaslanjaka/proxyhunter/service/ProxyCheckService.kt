package com.dimaslanjaka.proxyhunter.service

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.os.Build
import android.os.IBinder
import timber.log.Timber
import androidx.core.app.NotificationCompat
import com.dimaslanjaka.proxyhunter.ProxyCheckerActivity
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import com.dimaslanjaka.proxyhunter.data.ProxyDB
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import kotlinx.coroutines.*
import java.util.concurrent.atomic.AtomicInteger

class ProxyCheckService : Service() {

  private val serviceJob = SupervisorJob()
  private val serviceScope = CoroutineScope(Dispatchers.IO + serviceJob)
  private var checkJob: Job? = null

  private val CHANNEL_ID = "proxy_checker_channel"
  private val NOTIFICATION_ID = 1

  private var db: ProxyDB? = null
  private val checkedCount = AtomicInteger(0)
  private var totalCount = 0

  override fun onCreate() {
    super.onCreate()
    ProxyManager.setRunning(true)
    db = ProxyDB()
    createNotificationChannel()
  }

  override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
    ProxyManager.setRunning(true)

    if (checkJob?.isActive == true) {
      sendProgressBroadcast("", false, null, null, checkedCount.get(), totalCount)
      return START_STICKY
    }

    val proxies = ProxyManager.get()
    if (proxies.isEmpty()) {
      finishService()
      return START_NOT_STICKY
    }

    totalCount = proxies.size
    checkedCount.set(0)

    startForeground(NOTIFICATION_ID, createNotification(0, totalCount))

    checkJob = serviceScope.launch {
      try {
        for (proxy in proxies) {
          if (!isActive) break

          val proxyStr = proxy.toString()
          ProxyManager.setCurrentProxy(proxyStr)

          // Notify that we started checking this specific proxy
          sendBroadcast(Intent(ACTION_PROXY_CHECK_STARTED).apply {
              putExtra(EXTRA_PROXY, proxyStr)
          })

          val result = try {
            withTimeoutOrNull(35000) {
              ProxyChecker.check(proxy)
            } ?: ProxyChecker.CheckResult(false)
          } catch (e: Exception) {
            Timber.e(e, "Check failed for $proxy")
            ProxyChecker.CheckResult(false)
          }

          val currentChecked = checkedCount.incrementAndGet()

          // Store result to ProxyManager for UI observation
          ProxyManager.addResult(proxyStr, result)

          if (result.isWorking && result.type != null) {
            try {
              db?.upsertProxy(proxyStr, result.type, "active")?.get()
            } catch (e: Exception) {
              Timber.e(e, "DB update failed")
            }
          }

          updateNotification(currentChecked, totalCount)
          sendProgressBroadcast(proxyStr, result.isWorking, result.type, result.title, currentChecked, totalCount)
        }
      } catch (e: Exception) {
        Timber.e(e, "CheckJob failed")
      } finally {
        withContext(NonCancellable) {
          finishService()
        }
      }
    }

    return START_STICKY
  }

  private fun finishService() {
    ProxyManager.setRunning(false)
    ProxyManager.setCurrentProxy(null)
    sendBroadcast(Intent(ACTION_PROXY_CHECK_FINISHED))
    stopForeground(STOP_FOREGROUND_REMOVE)
    stopSelf()
  }

  private fun sendProgressBroadcast(proxy: String, isWorking: Boolean, type: String?, title: String?, checked: Int, total: Int) {
    val intent = Intent(ACTION_PROXY_CHECK_PROGRESS).apply {
      putExtra(EXTRA_PROXY, proxy)
      putExtra(EXTRA_IS_WORKING, isWorking)
      putExtra(EXTRA_TYPE, type)
      putExtra(EXTRA_TITLE, title)
      putExtra(EXTRA_CHECKED, checked)
      putExtra(EXTRA_TOTAL, total)
    }
    sendBroadcast(intent)
  }

  private fun createNotificationChannel() {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
      val channel = NotificationChannel(
        CHANNEL_ID,
        "Proxy Checker Service",
        NotificationManager.IMPORTANCE_LOW
      ).apply {
        description = "Shows progress of proxy checking"
      }
      val manager = getSystemService(NotificationManager::class.java)
      manager.createNotificationChannel(channel)
    }
  }

  private fun createNotification(current: Int, total: Int, contentText: String? = null): Notification {
    val intent = Intent(this, ProxyCheckerActivity::class.java).apply {
      flags = Intent.FLAG_ACTIVITY_SINGLE_TOP
    }
    val pendingIntent = PendingIntent.getActivity(
      this, 0, intent,
      PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
    )

    val text = contentText ?: "Checked $current of $total proxies"

    return NotificationCompat.Builder(this, CHANNEL_ID)
      .setContentTitle("Checking Proxies")
      .setContentText(text)
      .setSmallIcon(android.R.drawable.stat_notify_sync)
      .setOngoing(true)
      .setProgress(total, current, false)
      .setContentIntent(pendingIntent)
      .build()
  }

  private fun updateNotification(current: Int, total: Int, contentText: String? = null) {
    val notification = createNotification(current, total, contentText)
    val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
    manager.notify(NOTIFICATION_ID, notification)
  }

  override fun onBind(intent: Intent?): IBinder? = null

  override fun onDestroy() {
    ProxyManager.setRunning(false)
    ProxyManager.setCurrentProxy(null)
    serviceJob.cancel()
    db?.close()
    super.onDestroy()
  }

  companion object {
    const val ACTION_PROXY_CHECK_STARTED = "com.dimaslanjaka.proxyhunter.PROXY_CHECK_STARTED"
    const val ACTION_PROXY_CHECK_PROGRESS = "com.dimaslanjaka.proxyhunter.PROXY_CHECK_PROGRESS"
    const val ACTION_PROXY_CHECK_FINISHED = "com.dimaslanjaka.proxyhunter.PROXY_CHECK_FINISHED"
    const val EXTRA_PROXY = "extra_proxy"
    const val EXTRA_IS_WORKING = "extra_is_working"
    const val EXTRA_TYPE = "extra_type"
    const val EXTRA_TITLE = "extra_title"
    const val EXTRA_CHECKED = "extra_checked"
    const val EXTRA_TOTAL = "extra_total"
  }
}
