package com.dimaslanjaka.proxyhunter.data

import android.content.Context
import java.util.concurrent.Callable
import java.util.concurrent.Executors
import java.util.concurrent.Future

class ProxyDB {
  private val mysql: MySQLHelper?
  private val sqlite: SQLiteHelper?
  private val executor = Executors.newFixedThreadPool(5)

  constructor(
    host: String = "23.94.85.180",
    user: String = "proxyuser",
    pass: String = "proxypassword",
    dbName: String = "myproject",
    port: Int = 3306
  ) {
    this.mysql = MySQLHelper(host, user, pass, dbName, port)
    this.sqlite = null
  }

  constructor(sqlite: SQLiteHelper) {
    this.mysql = null
    this.sqlite = sqlite
  }

  constructor(sqlite_database: String, context: Context) {
    this.mysql = null
    this.sqlite = SQLiteHelper(context,sqlite_database)
  }

  private val isSQLite: Boolean get() = sqlite != null

  private fun query(sql: String, params: List<Any> = emptyList()): Future<List<Map<String, Any?>>> {
    return mysql?.query(sql, params) ?: sqlite!!.query(sql, params)
  }

  private fun update(sql: String, params: List<Any> = emptyList()): Future<Int> {
    return mysql?.update(sql, params) ?: sqlite!!.update(sql, params)
  }

  private fun now(): String = if (isSQLite) "datetime('now')" else "NOW()"
  private fun rand(): String = if (isSQLite) "RANDOM()" else "RAND()"
  private fun insertIgnore(): String = if (isSQLite) "INSERT OR IGNORE" else "INSERT IGNORE"

  fun getAllProxies(limit: Int? = null): Future<List<ProxyItem>> {
    val sql = if (limit != null) "SELECT * FROM proxies LIMIT ?" else "SELECT * FROM proxies"
    val params = if (limit != null) listOf(limit) else emptyList()

    return executor.submit(Callable {
      query(sql, params).get().map { mapRowToProxyItem(it) }
    })
  }

  fun getWorkingProxies(
    limit: Int = 100,
    offset: Int = 0,
    country: String? = null,
    city: String? = null,
    classification: String? = null,
    type: String? = null
  ): Future<List<ProxyItem>> {
    var sql = "SELECT * FROM proxies WHERE status = 'active'"
    val params = mutableListOf<Any>()

    if (!country.isNullOrBlank()) {
      sql += " AND country = ?"
      params.add(country)
    }
    if (!city.isNullOrBlank()) {
      sql += " AND city = ?"
      params.add(city)
    }
    if (!classification.isNullOrBlank()) {
      sql += " AND classification = ?"
      params.add(classification)
    }
    if (!type.isNullOrBlank()) {
      if (type.equals("SSL", ignoreCase = true)) {
        sql += " AND https = 'true'"
      } else {
        if (isSQLite) {
          sql += " AND type LIKE ?"
          params.add("%$type%")
        } else {
          // Use REGEXP for more robust matching of types within delimited strings
          sql += " AND type REGEXP ?"
          // Regex matches the type at start, after a delimiter, or at end/before a delimiter
          params.add("(^|[-, ])" + type + "($|[-, ])")
        }
      }
    }

    sql += " ORDER BY last_check DESC LIMIT ? OFFSET ?"
    params.add(limit)
    params.add(offset)

    return executor.submit(Callable {
      query(sql, params).get().map { mapRowToProxyItem(it) }
    })
  }

  fun getDeadProxies(limit: Int = 100, offset: Int = 0): Future<List<ProxyItem>> {
    val sql = "SELECT * FROM proxies WHERE status = 'dead' ORDER BY last_check DESC LIMIT ? OFFSET ?"
    return executor.submit(Callable {
      query(sql, listOf(limit, offset)).get().map { mapRowToProxyItem(it) }
    })
  }

  fun getUniqueCountries(): Future<List<String>> {
    val sql =
      "SELECT DISTINCT country FROM proxies WHERE status = 'active' AND country IS NOT NULL AND country != '' ORDER BY country ASC"
    return executor.submit(Callable {
      query(sql).get().mapNotNull { it["country"]?.toString() }
    })
  }

  fun getUniqueCities(country: String? = null): Future<List<String>> {
    var sql = "SELECT DISTINCT city FROM proxies WHERE status = 'active' AND city IS NOT NULL AND city != ''"
    val params = mutableListOf<Any>()
    if (!country.isNullOrBlank()) {
      sql += " AND country = ?"
      params.add(country)
    }
    sql += " ORDER BY city ASC"

    return executor.submit(Callable {
      query(sql, params).get().mapNotNull { it["city"]?.toString() }
    })
  }

  fun getUniqueClassifications(): Future<List<String>> {
    val sql =
      "SELECT DISTINCT classification FROM proxies WHERE status = 'active' AND classification IS NOT NULL AND classification != '' ORDER BY classification ASC"
    return executor.submit(Callable {
      query(sql).get().mapNotNull { it["classification"]?.toString() }
    })
  }

  fun getUniqueTypes(): Future<List<String>> {
    val sql = "SELECT DISTINCT type, https FROM proxies WHERE status = 'active'"
    return executor.submit(Callable {
      val rows = query(sql).get()
      val typeSet = mutableSetOf<String>()
      for (row in rows) {
        val rawType = row["type"] as? String
        val isHttps = row["https"]?.toString()?.lowercase() == "true"

        if (isHttps) typeSet.add("SSL")

        if (rawType != null) {
          rawType.split(",", "-", " ").forEach {
            val trimmed = it.trim().uppercase()
            if (trimmed.isNotEmpty()) typeSet.add(trimmed)
          }
        }
      }

      val preferredOrder = listOf("SSL", "SOCKS4", "SOCKS5", "SOCKS4A", "SOCKS5H")
      val result = preferredOrder.filter { it in typeSet }.toMutableList()
      val others = typeSet.filter { it !in preferredOrder }.sorted()
      result.addAll(others)

      result
    })
  }

  fun getUntestedProxies(limit: Int = 100, randomize: Boolean = true): Future<List<ProxyItem>> {
    var sql = "SELECT * FROM proxies WHERE status IS NULL OR status = 'untested' OR status = ''"

    if (randomize) {
      sql += " ORDER BY ${rand()}"
    }

    sql += " LIMIT ?"
    return executor.submit(Callable {
      query(sql, listOf(limit)).get().map { mapRowToProxyItem(it) }
    })
  }

  fun addProxy(proxy: String): Future<Boolean> {
    val sql = "${insertIgnore()} INTO proxies (proxy, status) VALUES (?, ?)"
    return executor.submit(Callable {
      update(sql, listOf(proxy, "untested")).get() > 0
    })
  }

  fun updateStatus(proxy: String, status: String): Future<Int> {
    val sql = "UPDATE proxies SET status = ?, last_check = ${now()} WHERE proxy = ?"
    return update(sql, listOf(status, proxy))
  }

  /**
   * Records a proxy with its detected type and status.
   * If the proxy doesn't exist, it will be inserted.
   */
  fun upsertProxy(proxy: String, type: String, status: String): Future<Boolean> {
    val sql = if (isSQLite) {
      """
            INSERT INTO proxies (proxy, type, status, last_check)
            VALUES (?, ?, ?, datetime('now'))
            ON CONFLICT(proxy) DO UPDATE SET
            type = ?, status = ?, last_check = datetime('now')
        """.trimIndent()
    } else {
      """
            INSERT INTO proxies (proxy, type, status, last_check)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            type = ?, status = ?, last_check = NOW()
        """.trimIndent()
    }
    return executor.submit(Callable {
      update(sql, listOf(proxy, type, status, type, status)).get() > 0
    })
  }

  fun testConnection(): Boolean {
    return try {
      getUniqueCountries().get()
      true
    } catch (e: Exception) {
      false
    }
  }

  private fun mapRowToProxyItem(row: Map<String, Any?>): ProxyItem {
    return ProxyItem(
      id = (row["id"] as? Number)?.toInt() ?: 0,
      proxy = row["proxy"] as? String ?: "",
      latency = row["latency"]?.toString(),
      lastCheck = (row["last_check"] ?: row["lastCheck"])?.toString(),
      type = row["type"]?.toString(),
      region = row["region"]?.toString(),
      city = row["city"]?.toString(),
      country = row["country"]?.toString(),
      timezone = row["timezone"]?.toString(),
      latitude = row["latitude"]?.toString(),
      longitude = row["longitude"]?.toString(),
      anonymity = row["anonymity"]?.toString(),
      https = row["https"]?.toString(),
      status = row["status"]?.toString(),
      private = row["private"]?.toString(),
      lang = row["lang"]?.toString(),
      useragent = row["useragent"]?.toString(),
      webglVendor = (row["webgl_vendor"] ?: row["webglVendor"])?.toString(),
      webglRenderer = (row["webgl_renderer"] ?: row["webglRenderer"])?.toString(),
      browserVendor = (row["browser_vendor"] ?: row["browserVendor"])?.toString(),
      username = row["username"]?.toString(),
      password = row["password"]?.toString(),
      classification = row["classification"]?.toString()
    )
  }

  fun close() {
    mysql?.close()
    sqlite?.close()
    executor.shutdown()
  }
}
