package com.dimaslanjaka.proxyhunter.data

import timber.log.Timber
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
    private val executor = Executors.newFixedThreadPool(5) // Increased pool size for concurrent updates

    @Synchronized
    private fun connect(): Connection? {
        try {
            if (connection == null || connection!!.isClosed || !isConnectionValid()) {
                Timber.d("Connecting to $host...")
                Class.forName("com.mysql.jdbc.Driver")
                // Added autoReconnect and longer timeouts
                val url = "jdbc:mysql://$host:$port/$dbName?useSSL=false&allowPublicKeyRetrieval=true&autoReconnect=true&connectTimeout=5000&socketTimeout=30000"
                connection = DriverManager.getConnection(url, user, pass)
            }
            return connection
        } catch (e: Exception) {
            Timber.e(e, "Connection failed: ${e.message}")
            return null
        }
    }

    private fun isConnectionValid(): Boolean {
        return try {
            connection?.isValid(2) ?: false
        } catch (e: Exception) {
            false
        }
    }

    fun <T> execute(task: (Connection) -> T): Future<T> {
        return executor.submit(Callable {
            val conn = connect() ?: throw SQLException("Could not establish connection to $host")
            try {
                task(conn)
            } catch (e: SQLException) {
                // If operation fails due to closed connection, try one more time
                if (e.message?.contains("connection closed", ignoreCase = true) == true) {
                    Timber.w("Connection lost, retrying...")
                    val retryConn = connect() ?: throw e
                    task(retryConn)
                } else {
                    throw e
                }
            }
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
            Timber.e(e, "Error closing connection")
        }
    }
}
