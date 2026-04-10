package com.dimaslanjaka.proxyhunter.data

import android.content.Context
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.checker.CheckerHttp
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
            _db ?: throw IllegalStateException("ProxyManager not initialized. Call initialize(context) first.")
        }

    private var _localDb: SQLiteHelper? = null
    val localDb: SQLiteHelper
        get() = _localDb ?: throw IllegalStateException("ProxyManager not initialized. Call initialize(context) first.")

    private var proxies: List<ProxyItem> = emptyList()

    // Results mapping: proxy string -> CheckResult (current session)
    private val _resultsFlow = MutableStateFlow<Map<String, Any>>(emptyMap())
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
            val dbFile = File(context.filesDir, "local_proxy.db")
            val sqliteHelper = SQLiteHelper(context.applicationContext, dbFile.absolutePath)
            _localDb = sqliteHelper

            // Initialize proxies table
            sqliteHelper.update("""
                CREATE TABLE IF NOT EXISTS proxies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proxy TEXT UNIQUE,
                    status TEXT DEFAULT 'untested',
                    latency TEXT,
                    last_check DATETIME,
                    type TEXT,
                    region TEXT,
                    city TEXT,
                    country TEXT,
                    timezone TEXT,
                    latitude TEXT,
                    longitude TEXT,
                    anonymity TEXT,
                    https TEXT,
                    private TEXT,
                    lang TEXT,
                    useragent TEXT,
                    webgl_vendor TEXT,
                    webgl_renderer TEXT,
                    browser_vendor TEXT,
                    username TEXT,
                    password TEXT,
                    classification TEXT
                )
            """.trimIndent()).get()

            // Initialize results table if it doesn't exist
            sqliteHelper.update("""
                CREATE TABLE IF NOT EXISTS proxy_results (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proxy TEXT,
                    is_working INTEGER,
                    type TEXT,
                    title TEXT,
                    http INTEGER DEFAULT 0,
                    tcp INTEGER DEFAULT 0,
                    ssl INTEGER DEFAULT 0,
                    latency INTEGER DEFAULT 0,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            """.trimIndent()).get()

            // Run migrations
            migrate(sqliteHelper)

            synchronized(this) {
                _db?.close()
                _db = ProxyDB(sqliteHelper)
            }
        }
    }

    private fun migrate(db: SQLiteHelper) {
        try {
            // Migrate proxy_results
            val resultsColumns = db.query("PRAGMA table_info(proxy_results)").get()
            val resultsNames = resultsColumns.map { it["name"] as String }

            if (!resultsNames.contains("http")) db.update("ALTER TABLE proxy_results ADD COLUMN http INTEGER DEFAULT 0").get()
            if (!resultsNames.contains("tcp")) db.update("ALTER TABLE proxy_results ADD COLUMN tcp INTEGER DEFAULT 0").get()
            if (!resultsNames.contains("ssl")) db.update("ALTER TABLE proxy_results ADD COLUMN ssl INTEGER DEFAULT 0").get()
            if (!resultsNames.contains("latency")) db.update("ALTER TABLE proxy_results ADD COLUMN latency INTEGER DEFAULT 0").get()

            // Migrate proxies
            val proxyColumns = db.query("PRAGMA table_info(proxies)").get()
            val proxyNames = proxyColumns.map { it["name"] as String }
            if (!proxyNames.contains("status")) {
                db.update("ALTER TABLE proxies ADD COLUMN status TEXT DEFAULT 'untested'").get()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    @JvmStatic
    fun getUntestedProxies(limit: Int = 100): Future<List<ProxyItem>> {
        return db.getUntestedProxies(limit)
    }

    @JvmStatic
    fun getWorkingProxies(limit: Int = 100, offset: Int = 0): Future<List<ProxyItem>> {
        return db.getWorkingProxies(limit, offset)
    }

    @JvmStatic
    fun getDeadProxies(limit: Int = 100, offset: Int = 0): Future<List<ProxyItem>> {
        return db.getDeadProxies(limit, offset)
    }

    @JvmStatic
    fun updateProxyStatus(proxy: String, status: String): Future<Int> {
        return db.updateStatus(proxy, status)
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
    fun addResult(proxy: String, result: ProxyChecker.Result) {
        val currentMap = _resultsFlow.value.toMutableMap()
        currentMap[proxy] = result
        _resultsFlow.value = currentMap

        val type = result.type ?: ""
        val isHttp = type.contains("http", ignoreCase = true)
        val isTcp = result.isWorking
        val isSsl = type.contains("https", ignoreCase = true)
        val status = if (result.isWorking) "active" else "dead"

        // Update main proxies table
        db.upsertProxy(proxy, type, status)

        // Store result to SQLite history
        localDb.update("INSERT INTO proxy_results (proxy, is_working, type, title, http, tcp, ssl, latency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            listOf(
                proxy,
                if (result.isWorking) 1 else 0,
                type,
                result.title ?: "",
                if (isHttp) 1 else 0,
                if (isTcp) 1 else 0,
                if (isSsl) 1 else 0,
                0
            ))
    }

    @JvmStatic
    fun addResult(result: CheckerHttp.Result) {
        val proxy = result.proxy
        val currentMap = _resultsFlow.value.toMutableMap()
        currentMap[proxy] = result
        _resultsFlow.value = currentMap

        val isWorking = result.httpOk || result.httpsOk
        val typeList = mutableListOf<String>()
        if (result.httpOk) typeList.add("http")
        if (result.httpsOk) typeList.add("https")
        val type = typeList.joinToString("-")
        val status = if (isWorking) "active" else "dead"

        // Update main proxies table
        db.upsertProxy(proxy, type, status)

        // Store result to SQLite history
        localDb.update("INSERT INTO proxy_results (proxy, is_working, type, title, http, tcp, ssl, latency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            listOf(
                proxy,
                if (isWorking) 1 else 0,
                type,
                result.error ?: "",
                if (result.httpOk) 1 else 0,
                if (result.tcpOk) 1 else 0,
                if (result.httpsOk) 1 else 0,
                result.latencyMs
            ))
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
