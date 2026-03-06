package com.dimaslanjaka.proxyhunter.checker

import com.dimaslanjaka.proxyhunter.data.ProxyItem
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.IOException
import java.net.InetSocketAddress
import java.net.Proxy
import java.util.concurrent.TimeUnit

object ProxyChecker {
    @JvmStatic
    fun check(proxyItem: ProxyItem): Boolean {
        val p = Proxy(
            Proxy.Type.HTTP,
            InetSocketAddress(proxyItem.host, proxyItem.port)
        )

        val client = OkHttpClient.Builder()
            .proxy(p)
            .connectTimeout(5, TimeUnit.SECONDS)
            .build()

        val request = Request.Builder()
            .url("https://www.google.com")
            .build()

        return try {
            client.newCall(request).execute().use { response ->
                response.isSuccessful
            }
        } catch (e: IOException) {
            false
        }
    }
}
