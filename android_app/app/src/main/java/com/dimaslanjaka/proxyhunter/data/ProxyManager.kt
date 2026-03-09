package com.dimaslanjaka.proxyhunter.data

import android.content.Context
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.io.File
import java.util.concurrent.Future

object ProxyManager {
    private var _prefs: LocalSharedPrefs? = null
    val prefs: LocalSharedPrefs
        get() = _prefs ?: throw IllegalStateException("ProxyManager not initialized. Call initialize(context) first.")

    private var _db: ProxyDB? = null
    val db: ProxyDB
        get() = synchronized(this) {
            _db ?: ProxyDB().also { _db = it }
        }

    private var _localDb: SQLiteHelper? = null
    val localDb: SQLiteHelper
        get() = _localDb ?: throw IllegalStateException("ProxyManager not initialized. Call initialize(context) first.")

    private var proxies: List<ProxyItem> = emptyList()

    // Results mapping: proxy string -> CheckResult (current session)
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
            val dbFile = File(context.filesDir, "proxy_results.db")
            _localDb = SQLiteHelper(context.applicationContext, dbFile.absolutePath)

            // Initialize results table if it doesn't exist
            _localDb?.update("CREATE TABLE IF NOT EXISTS proxy_results (" +
                "id INTEGER PRIMARY KEY AUTOINCREMENT, " +
                "proxy TEXT, " +
                "is_working INTEGER, " +
                "type TEXT, " +
                "title TEXT, " +
                "timestamp DATETIME DEFAULT CURRENT_TIMESTAMP" +
                ")")

            synchronized(this) {
                _db?.close()
                _db = ProxyDB()
            }
        }
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

        // Store result to SQLite
        localDb.update("INSERT INTO proxy_results (proxy, is_working, type, title) VALUES (?, ?, ?, ?)",
            listOf(proxy, if (result.isWorking) 1 else 0, result.type ?: "", result.title ?: ""))
    }

    @JvmStatic
    fun getAllLocalResults(): Future<List<Map<String, Any?>>> {
        return localDb.query("SELECT * FROM proxy_results ORDER BY timestamp DESC")
    }

    @JvmStatic
    fun clearResults() {
        _resultsFlow.value = emptyMap()
        localDb.update("DELETE FROM proxy_results")
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
