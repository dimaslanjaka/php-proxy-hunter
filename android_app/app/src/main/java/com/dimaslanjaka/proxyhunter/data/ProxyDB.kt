package com.dimaslanjaka.proxyhunter.data

import java.util.concurrent.Future

class ProxyDB(
    host: String = "23.94.85.180",
    user: String = "proxyuser",
    pass: String = "proxypassword",
    dbName: String = "myproject",
    port: Int = 3306
) {
    private val db = MySQLHelper(host, user, pass, dbName, port)

    fun getAllProxies(limit: Int? = null): Future<List<ProxyItem>> {
        val sql = if (limit != null) "SELECT * FROM proxies LIMIT ?" else "SELECT * FROM proxies"
        val params = if (limit != null) listOf(limit) else emptyList()

        return db.execute { conn ->
            val stmt = conn.prepareStatement(sql)
            params.forEachIndexed { index, param ->
                stmt.setObject(index + 1, param)
            }
            val rs = stmt.executeQuery()
            val list = mutableListOf<ProxyItem>()
            while (rs.next()) {
                list.add(mapResultSetToProxyItem(rs))
            }
            rs.close()
            stmt.close()
            list
        }
    }

    fun getWorkingProxies(
        limit: Int = 100,
        offset: Int = 0,
        country: String? = null,
        city: String? = null,
        classification: String? = null
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

        sql += " ORDER BY last_check DESC LIMIT ? OFFSET ?"
        params.add(limit)
        params.add(offset)

        return db.execute { conn ->
            val stmt = conn.prepareStatement(sql)
            params.forEachIndexed { index, param ->
                stmt.setObject(index + 1, param)
            }
            val rs = stmt.executeQuery()
            val list = mutableListOf<ProxyItem>()
            while (rs.next()) {
                list.add(mapResultSetToProxyItem(rs))
            }
            rs.close()
            stmt.close()
            list
        }
    }

    fun getUniqueCountries(): Future<List<String>> {
        val sql = "SELECT DISTINCT country FROM proxies WHERE status = 'active' AND country IS NOT NULL AND country != '' ORDER BY country ASC"
        return db.execute { conn ->
            val stmt = conn.prepareStatement(sql)
            val rs = stmt.executeQuery()
            val list = mutableListOf<String>()
            while (rs.next()) {
                list.add(rs.getString("country"))
            }
            rs.close()
            stmt.close()
            list
        }
    }

    fun getUniqueCities(country: String? = null): Future<List<String>> {
        var sql = "SELECT DISTINCT city FROM proxies WHERE status = 'active' AND city IS NOT NULL AND city != ''"
        val params = mutableListOf<Any>()
        if (!country.isNullOrBlank()) {
            sql += " AND country = ?"
            params.add(country)
        }
        sql += " ORDER BY city ASC"

        return db.execute { conn ->
            val stmt = conn.prepareStatement(sql)
            params.forEachIndexed { index, param ->
                stmt.setObject(index + 1, param)
            }
            val rs = stmt.executeQuery()
            val list = mutableListOf<String>()
            while (rs.next()) {
                list.add(rs.getString("city"))
            }
            rs.close()
            stmt.close()
            list
        }
    }

    fun getUniqueClassifications(): Future<List<String>> {
        val sql = "SELECT DISTINCT classification FROM proxies WHERE status = 'active' AND classification IS NOT NULL AND classification != '' ORDER BY classification ASC"
        return db.execute { conn ->
            val stmt = conn.prepareStatement(sql)
            val rs = stmt.executeQuery()
            val list = mutableListOf<String>()
            while (rs.next()) {
                list.add(rs.getString("classification"))
            }
            rs.close()
            stmt.close()
            list
        }
    }

    fun getUntestedProxies(limit: Int = 100): Future<List<ProxyItem>> {
        val sql = "SELECT * FROM proxies WHERE status IS NULL OR status = 'untested' OR status = '' LIMIT ?"
        return db.execute { conn ->
            val stmt = conn.prepareStatement(sql)
            stmt.setInt(1, limit)
            val rs = stmt.executeQuery()
            val list = mutableListOf<ProxyItem>()
            while (rs.next()) {
                list.add(mapResultSetToProxyItem(rs))
            }
            rs.close()
            stmt.close()
            list
        }
    }

    fun addProxy(proxy: String): Future<Boolean> {
        val sql = "INSERT IGNORE INTO proxies (proxy, status) VALUES (?, ?)"
        return db.update(sql, listOf(proxy, "untested")).let { future ->
            db.execute { future.get() > 0 }
        }
    }

    fun updateStatus(proxy: String, status: String): Future<Int> {
        val sql = "UPDATE proxies SET status = ?, last_check = NOW() WHERE proxy = ?"
        return db.update(sql, listOf(status, proxy))
    }

    /**
     * Records a proxy with its detected type and status.
     * If the proxy doesn't exist, it will be inserted.
     */
    fun upsertProxy(proxy: String, type: String, status: String): Future<Boolean> {
        val sql = """
            INSERT INTO proxies (proxy, type, status, last_check)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            type = ?, status = ?, last_check = NOW()
        """.trimIndent()
        return db.update(sql, listOf(proxy, type, status, type, status)).let { future ->
            db.execute { future.get() > 0 }
        }
    }

    private fun mapResultSetToProxyItem(rs: java.sql.ResultSet): ProxyItem {
        return ProxyItem(
            id = rs.getInt("id"),
            proxy = rs.getString("proxy"),
            latency = rs.getString("latency"),
            lastCheck = rs.getString("last_check"),
            type = rs.getString("type"),
            region = rs.getString("region"),
            city = rs.getString("city"),
            country = rs.getString("country"),
            timezone = rs.getString("timezone"),
            latitude = rs.getString("latitude"),
            longitude = rs.getString("longitude"),
            anonymity = rs.getString("anonymity"),
            https = rs.getString("https"),
            status = rs.getString("status"),
            private = rs.getString("private"),
            lang = rs.getString("lang"),
            useragent = rs.getString("useragent"),
            webglVendor = rs.getString("webgl_vendor"),
            webglRenderer = rs.getString("webgl_renderer"),
            browserVendor = rs.getString("browser_vendor"),
            username = rs.getString("username"),
            password = rs.getString("password"),
            classification = try { rs.getString("classification") } catch (e: Exception) { null }
        )
    }

    fun close() {
        db.close()
    }
}
