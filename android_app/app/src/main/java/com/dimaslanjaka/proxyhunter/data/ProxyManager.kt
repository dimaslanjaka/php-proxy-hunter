package com.dimaslanjaka.proxyhunter.data

import android.content.Context
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
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



    @JvmStatic
    fun initialize(context: Context) {
        if (_prefs == null) {
            _prefs = LocalSharedPrefs.initialize(context.applicationContext, "proxy_checker_prefs")
            synchronized(this) {
                _db?.close()
                _db = ProxyDB()
            }
        }
    }

    // Tailscale logic removed: always use the configured default DB

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
