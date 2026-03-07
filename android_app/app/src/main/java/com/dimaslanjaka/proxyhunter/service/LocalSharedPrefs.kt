package com.dimaslanjaka.proxyhunter.service

import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit

class LocalSharedPrefs private constructor(context: Context, name: String) {
    private val sharedPreferences: SharedPreferences =
        context.getSharedPreferences(name, Context.MODE_PRIVATE)

    fun getString(key: String, defaultValue: String?): String? {
        return sharedPreferences.getString(key, defaultValue)
    }

    fun putString(key: String, value: String) {
        sharedPreferences.edit { putString(key, value) }
    }

    companion object {
        fun initialize(context: Context, name: String): LocalSharedPrefs {
            return LocalSharedPrefs(context, name)
        }
    }
}
