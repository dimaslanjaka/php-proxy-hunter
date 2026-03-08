package com.dimaslanjaka.proxyhunter.data

import android.content.Context
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import com.google.firebase.database.DataSnapshot
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import timber.log.Timber

object ProxyManager {
    private var _prefs: LocalSharedPrefs? = null
    val prefs: LocalSharedPrefs
        get() = _prefs ?: throw IllegalStateException("ProxyManager not initialized. Call initialize(context) first.")

    private var _db: ProxyDB? = null
    val db: ProxyDB
        get() = synchronized(this) {
            _db ?: ProxyDB().also { _db = it }
        }

    private var proxies: List<ProxyItem> = emptyList()

    // Results mapping: proxy string -> CheckResult
    private val _resultsFlow = MutableStateFlow<Map<String, ProxyChecker.CheckResult>>(emptyMap())
    val resultsFlow = _resultsFlow.asStateFlow()

    // Service running status
    private val _isRunningFlow = MutableStateFlow(false)
    val isRunningFlow = _isRunningFlow.asStateFlow()

    // Current proxy being checked
    private val _currentProxyFlow = MutableStateFlow<String?>(null)
    val currentProxyFlow = _currentProxyFlow.asStateFlow()

    // Tailscale Status Flow
    private val _tailscaleStatusFlow = MutableStateFlow<TailscaleStatus?>(null)
    val tailscaleStatusFlow = _tailscaleStatusFlow.asStateFlow()

    @JvmStatic
    fun initialize(context: Context) {
        if (_prefs == null) {
            _prefs = LocalSharedPrefs.initialize(context.applicationContext, "proxy_checker_prefs")
            observeTailscaleStatus()
        }
    }

    private fun observeTailscaleStatus() {
        val database = FirebaseDatabase.getInstance()
        val ref = database.getReference("tailscale_status")

        ref.addValueEventListener(object : ValueEventListener {
            override fun onDataChange(snapshot: DataSnapshot) {
                val status = snapshot.getValue(TailscaleStatus::class.java)
                _tailscaleStatusFlow.value = status
                Timber.d("Tailscale status updated: $status")

                status?.let {
                    // Host is taken from the 'ip' property
                    val host = it.ip ?: ""
                    val user = it.mysqlUser ?: ""
                    val pass = it.mysqlPass ?: ""
                    val dbName = it.mysqlDbname ?: ""

                    synchronized(this@ProxyManager) {
                        _db?.close()

                        var success = false
                        if (host.isNotEmpty()) {
                            val tailscaleDb = ProxyDB(host, user, pass, dbName)
                            if (tailscaleDb.testConnection()) {
                                _db = tailscaleDb
                                success = true
                                Timber.d("Connected using Tailscale IP: $host")
                            } else {
                                tailscaleDb.close()
                                Timber.w("Tailscale connection failed for $host")
                            }
                        }

                        if (!success) {
                            // Fallback to primary server
                            _db = ProxyDB(
                                host = "23.94.85.180",
                                user = "proxyuser",
                                pass = "proxypassword",
                                dbName = "myproject"
                            )
                            Timber.d("Using fallback database connection: 23.94.85.180")
                        }
                    }
                }
            }

            override fun onCancelled(error: DatabaseError) {
                Timber.e(error.toException(), "Failed to read Tailscale status")
            }
        })
    }

    @JvmStatic
    fun set(list: List<ProxyItem>) {
        proxies = list
        _resultsFlow.value = emptyMap()
        _currentProxyFlow.value = null
    }

    @JvmStatic
    fun get(): List<ProxyItem> {
        return proxies
    }

    @JvmStatic
    fun addResult(proxy: String, result: ProxyChecker.CheckResult) {
        val currentMap = _resultsFlow.value.toMutableMap()
        currentMap[proxy] = result
        _resultsFlow.value = currentMap
    }

    @JvmStatic
    fun setRunning(running: Boolean) {
        _isRunningFlow.value = running
    }

    @JvmStatic
    fun setCurrentProxy(proxy: String?) {
        _currentProxyFlow.value = proxy
    }
}
