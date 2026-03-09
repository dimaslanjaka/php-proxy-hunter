package com.dimaslanjaka.proxyhunter.data

import android.content.Context
import android.database.Cursor
import android.database.sqlite.SQLiteDatabase
import timber.log.Timber
import java.io.File
import java.sql.SQLException
import java.util.concurrent.Callable
import java.util.concurrent.Executors
import java.util.concurrent.Future

/**
 * A helper class for interacting with SQLite databases, styled after MySQLHelper.
 */
class SQLiteHelper(private val context: Context, private val dbPath: String) {
    private var connection: SQLiteDatabase? = null
    private val executor = Executors.newFixedThreadPool(5)

    @Synchronized
    private fun connect(): SQLiteDatabase? {
        try {
            if (connection == null || !connection!!.isOpen) {
                Timber.d("Connecting to SQLite database at $dbPath...")
                val file = File(dbPath)
                file.parentFile?.mkdirs()
                connection = SQLiteDatabase.openOrCreateDatabase(dbPath, null)
                connection?.execSQL("PRAGMA foreign_keys = ON")
            }
            return connection
        } catch (e: Exception) {
            Timber.e(e, "Connection failed to SQLite database at $dbPath: ${e.message}")
            return null
        }
    }

    private fun isConnectionValid(): Boolean {
        return connection?.isOpen ?: false
    }

    fun <T> execute(task: (SQLiteDatabase) -> T): Future<T> {
        return executor.submit(Callable {
            val conn = connect() ?: throw SQLException("Could not establish connection to $dbPath")
            synchronized(conn) {
                task(conn)
            }
        })
    }

    fun query(sql: String, params: List<Any> = emptyList()): Future<List<Map<String, Any?>>> {
        return execute { conn ->
            val strParams = params.map { it.toString() }.toTypedArray()
            val cursor = conn.rawQuery(sql, strParams)
            val result = mutableListOf<Map<String, Any?>>()
            cursor.use {
                val columnNames = it.columnNames
                while (it.moveToNext()) {
                    val row = mutableMapOf<String, Any?>()
                    for (name in columnNames) {
                        val index = it.getColumnIndex(name)
                        when (it.getType(index)) {
                            Cursor.FIELD_TYPE_NULL -> row[name] = null
                            Cursor.FIELD_TYPE_INTEGER -> row[name] = it.getLong(index)
                            Cursor.FIELD_TYPE_FLOAT -> row[name] = it.getDouble(index)
                            Cursor.FIELD_TYPE_STRING -> row[name] = it.getString(index)
                            Cursor.FIELD_TYPE_BLOB -> row[name] = it.getBlob(index)
                        }
                    }
                    result.add(row)
                }
            }
            result
        }
    }

    fun update(sql: String, params: List<Any> = emptyList()): Future<Int> {
        return execute { conn ->
            val stmt = conn.compileStatement(sql)
            params.forEachIndexed { index, param ->
                val bindIndex = index + 1
                when (param) {
                    is String -> stmt.bindString(bindIndex, param)
                    is Long -> stmt.bindLong(bindIndex, param)
                    is Int -> stmt.bindLong(bindIndex, param.toLong())
                    is Double -> stmt.bindDouble(bindIndex, param)
                    is Float -> stmt.bindDouble(bindIndex, param.toDouble())
                    is ByteArray -> stmt.bindBlob(bindIndex, param)
                    null -> stmt.bindNull(bindIndex)
                    else -> stmt.bindString(bindIndex, param.toString())
                }
            }
            val affectedRows = stmt.executeUpdateDelete()
            stmt.close()
            affectedRows
        }
    }

    fun close() {
        executor.shutdown()
        try {
            connection?.close()
        } catch (e: Exception) {
            Timber.e(e, "Error closing connection")
        }
    }
}
