package com.dimaslanjaka.proxyhunter.service

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import timber.log.Timber
import androidx.core.app.NotificationCompat
import com.dimaslanjaka.proxyhunter.ProxyCheckerActivity
import com.dimaslanjaka.proxy.Tun2SocksCompatibilityTest
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import kotlinx.coroutines.*
import java.util.concurrent.atomic.AtomicInteger

class Tun2socksCompatibilityTestService : Service() {

    private val serviceJob = SupervisorJob()
    private val serviceScope = CoroutineScope(Dispatchers.IO + serviceJob)
    private var checkJob: Job? = null
    private var isPriorityMode = false

    private val CHANNEL_ID = "tun2socks_compatibility_channel"
    private val NOTIFICATION_ID = 2
    private val SERVICE_NAME = "Tun2socksCompatibilityTestService"

    private val checkedCount = AtomicInteger(0)
    private var totalCount = 0

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        // Call startForeground immediately in onCreate to satisfy Android 12+ requirements
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(
                NOTIFICATION_ID,
                createNotification(0, 0, "Initializing..."),
                ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE
            )
        } else {
            startForeground(NOTIFICATION_ID, createNotification(0, 0, "Initializing..."))
        }

        ProxyManager.initialize(this)
        ProxyManager.setRunning(SERVICE_NAME, true)
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val isPriority = intent?.getBooleanExtra(EXTRA_PRIORITY, false) ?: false

        if (isPriority) {
            Timber.d("Priority compatibility test requested, cancelling current job if any")
            checkJob?.cancel()
            isPriorityMode = true
        } else if (checkJob?.isActive == true) {
            Timber.d("Service already running, ignoring start request")
            return START_STICKY
        } else {
            isPriorityMode = false
        }

        ProxyManager.setRunning(SERVICE_NAME, true)

        checkJob = serviceScope.launch {
            try {
                var currentProxies = ProxyManager.get()
                val db = ProxyManager.db
                val prefs = ProxyManager.prefs

                // Get test configurations from preferences
                val strictCheck = prefs.getBoolean("strict_check", true)
                val requireUDP = prefs.getBoolean("require_udp", true)
                val requireDNS = prefs.getBoolean("require_dns", true)

                // If no proxies provided and auto-check is enabled AND we are not in priority mode, fetch from DB
                if (currentProxies.isEmpty() && prefs.getBoolean("auto_check_proxies", false) && !isPriorityMode) {
                    Timber.d("No proxies in manager, fetching untested proxies for auto-compatibility check")
                    currentProxies = db.getUntestedProxies(100).get()
                    if (currentProxies.isNotEmpty()) {
                        ProxyManager.set(currentProxies)
                    }
                }

                if (currentProxies.isEmpty()) {
                    Timber.d("No proxies to check, finishing service")
                    withContext(Dispatchers.Main) {
                        finishService()
                    }
                    return@launch
                }

                totalCount = currentProxies.size
                checkedCount.set(0)

                // Update notification now that we have the count
                updateNotification(0, totalCount)

                for (proxy in currentProxies) {
                    if (!isActive) {
                        Timber.d("Compatibility job cancelled/inactive, stopping loop")
                        break
                    }

                    val proxyStr = proxy.toString()
                    ProxyManager.setCheckingProxy(proxyStr, SERVICE_NAME)

                    // Notify that we started checking this specific proxy
                    sendBroadcast(Intent(ACTION_COMPATIBILITY_CHECK_STARTED).apply {
                        putExtra(EXTRA_PROXY, proxyStr)
                    })

                    val testResult = try {
                        withTimeoutOrNull(45000) {
                            Tun2SocksCompatibilityTest.comprehensiveTest(proxyStr, 15000)
                        } ?: Tun2SocksCompatibilityTest.TestResult.failure("Timeout", "UNKNOWN")
                    } catch (e: Exception) {
                        Timber.e(e, "Compatibility test failed for $proxy")
                        Tun2SocksCompatibilityTest.TestResult.failure(e.message ?: "Unknown error", "UNKNOWN")
                    }

                    val currentChecked = checkedCount.incrementAndGet()

                    // Convert Tun2SocksCompatibilityTest.TestResult to ProxyChecker.CheckResult
                    val isWorking = if (strictCheck) {
                        testResult.isTun2SocksCompatible(requireUDP, requireDNS)
                    } else {
                        testResult.isTun2SocksCompatible()
                    }

                    val type = if (isWorking) testResult.proxyType else null
                    val title = if (isWorking) testResult.compatibilitySummary else testResult.errorMessage

                    val result = ProxyChecker.Result(
                        isWorking = isWorking,
                        type = type,
                        title = title
                    )

                    // Store result to ProxyManager for UI observation
                    ProxyManager.addResult(proxyStr, result)

                    // Clear current proxy after result is added
                    ProxyManager.clearCheckingProxy(proxyStr, SERVICE_NAME)

                    if (isWorking) {
                        try {
                            db.upsertProxy(proxyStr, type ?: "SOCKS5", "active").get()
                        } catch (e: Exception) {
                            Timber.e(e, "DB update failed")
                        }
                    }

                    updateNotification(currentChecked, totalCount)
                    sendProgressBroadcast(proxyStr, isWorking, type, title, currentChecked, totalCount)
                }
            } catch (e: CancellationException) {
                Timber.d("CompatibilityJob was cancelled")
            } catch (e: Exception) {
                Timber.e(e, "CompatibilityJob failed")
            } finally {
                withContext(NonCancellable) {
                    withContext(Dispatchers.Main) {
                        finishService()
                    }
                }
            }
        }

        return START_STICKY
    }

    private fun finishService() {
        val autoCheckEnabled = ProxyManager.prefs.getBoolean("auto_check_proxies", false)

        // Only continue auto-check if we were NOT in priority (manual) mode
        if (autoCheckEnabled && !isPriorityMode && checkJob?.isCancelled != true) {
            serviceScope.launch {
                try {
                    val untested = ProxyManager.db.getUntestedProxies(100).get()
                    if (untested.isNotEmpty()) {
                        Timber.d("Auto-compatibility check continuing with ${untested.size} more proxies")
                        ProxyManager.set(untested)
                        val intent = Intent(this@Tun2socksCompatibilityTestService, Tun2socksCompatibilityTestService::class.java)
                        startService(intent)
                    } else {
                        Timber.d("No more untested proxies, stopping service")
                        actuallyStopService()
                    }
                } catch (e: Exception) {
                    Timber.e(e, "Failed to fetch more proxies for auto-check")
                    actuallyStopService()
                }
            }
        } else {
            actuallyStopService()
        }
    }

    private fun actuallyStopService() {
        ProxyManager.setCheckingProxy(null, SERVICE_NAME)
        ProxyManager.setRunning(SERVICE_NAME, false)
        sendBroadcast(Intent(ACTION_COMPATIBILITY_CHECK_FINISHED))
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    private fun sendProgressBroadcast(proxy: String, isWorking: Boolean, type: String?, title: String?, checked: Int, total: Int) {
        val intent = Intent(ACTION_COMPATIBILITY_CHECK_PROGRESS).apply {
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
                "Tun2socks Compatibility Service",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Shows progress of compatibility testing"
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
            .setContentTitle("Checking Tun2Socks Compatibility")
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
        ProxyManager.setCheckingProxy(null, SERVICE_NAME)
        ProxyManager.setRunning(SERVICE_NAME, false)
        serviceJob.cancel()
        super.onDestroy()
    }

    companion object {
        const val ACTION_COMPATIBILITY_CHECK_STARTED = "com.dimaslanjaka.proxyhunter.COMPATIBILITY_CHECK_STARTED"
        const val ACTION_COMPATIBILITY_CHECK_PROGRESS = "com.dimaslanjaka.proxyhunter.COMPATIBILITY_CHECK_PROGRESS"
        const val ACTION_COMPATIBILITY_CHECK_FINISHED = "com.dimaslanjaka.proxyhunter.COMPATIBILITY_CHECK_FINISHED"
        const val EXTRA_PROXY = "extra_proxy"
        const val EXTRA_IS_WORKING = "extra_is_working"
        const val EXTRA_TYPE = "extra_type"
        const val EXTRA_TITLE = "extra_title"
        const val EXTRA_CHECKED = "extra_checked"
        const val EXTRA_TOTAL = "extra_total"
        const val EXTRA_PRIORITY = "extra_priority"
    }
}
