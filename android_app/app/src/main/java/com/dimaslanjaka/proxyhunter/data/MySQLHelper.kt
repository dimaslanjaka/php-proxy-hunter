package com.dimaslanjaka.proxyhunter.data

import android.util.Log
import java.sql.Connection
import java.sql.DriverManager
import java.sql.SQLException
import java.util.concurrent.Callable
import java.util.concurrent.Executors
import java.util.concurrent.Future

class MySQLHelper(
    private val host: String,
    private val user: String,
    private val pass: String,
    private val dbName: String,
    private val port: Int = 3306
) {
    private var connection: Connection? = null
    private val executor = Executors.newSingleThreadExecutor()

    private fun connect(): Connection? {
        return try {
            if (connection == null || connection!!.isClosed) {
                // Using MySQL driver which is more compatible with Android
                Class.forName("com.mysql.jdbc.Driver")
                val url = "jdbc:mysql://$host:$port/$dbName?useSSL=false&allowPublicKeyRetrieval=true"
                connection = DriverManager.getConnection(url, user, pass)
            }
            connection
        } catch (e: Exception) {
            Log.e("MySQLHelper", "Connection failed: ${e.message}", e)
            null
        }
    }

    fun <T> execute(task: (Connection) -> T): Future<T> {
        return executor.submit(Callable {
            val conn = connect() ?: throw SQLException("Could not establish connection to $host")
            task(conn)
        })
    }

    fun query(sql: String, params: List<Any> = emptyList()): Future<List<Map<String, Any?>>> {
        return execute { conn ->
            val stmt = conn.prepareStatement(sql)
            params.forEachIndexed { index, param ->
                stmt.setObject(index + 1, param)
            }
            val rs = stmt.executeQuery()
            val result = mutableListOf<Map<String, Any?>>()
            val metaData = rs.metaData
            val columnCount = metaData.columnCount
            while (rs.next()) {
                val row = mutableMapOf<String, Any?>()
                for (i in 1..columnCount) {
                    row[metaData.getColumnName(i)] = rs.getObject(i)
                }
                result.add(row)
            }
            rs.close()
            stmt.close()
            result
        }
    }

    fun update(sql: String, params: List<Any> = emptyList()): Future<Int> {
        return execute { conn ->
            val stmt = conn.prepareStatement(sql)
            params.forEachIndexed { index, param ->
                stmt.setObject(index + 1, param)
            }
            val affectedRows = stmt.executeUpdate()
            stmt.close()
            affectedRows
        }
    }

    fun close() {
        executor.shutdown()
        try {
            connection?.close()
        } catch (e: SQLException) {
            Log.e("MySQLHelper", "Error closing connection", e)
        }
    }
}
