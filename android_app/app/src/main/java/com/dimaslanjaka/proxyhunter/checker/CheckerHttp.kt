package com.dimaslanjaka.proxyhunter.checker

import kotlinx.coroutines.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.channelFlow
import kotlinx.coroutines.sync.Semaphore
import kotlinx.coroutines.sync.withPermit
import okhttp3.*
import java.net.*
import java.util.concurrent.TimeUnit
import javax.net.ssl.SSLException

class CheckerHttp(
  private val timeoutMs: Int = 5000,
  private val defaultHttpEndpoint: String = "http://httpbin.org/ip",
  private val defaultHttpsEndpoint: String = "https://httpbin.org/ip"
) {

  /**
   * Shared connection pool (IMPORTANT for performance)
   */
  private val connectionPool = ConnectionPool(100, 5, TimeUnit.MINUTES)

  /**
   * Result model (inner class as requested)
   */
  data class Result(
    val proxy: String,
    val tcpOk: Boolean,
    val httpOk: Boolean,
    val httpsOk: Boolean,
    val latencyMs: Long,
    val error: String? = null
  )

  /**
   * Check single proxy
   */
  suspend fun check(
    proxyStr: String,
    httpEndpoint: String = defaultHttpEndpoint,
    httpsEndpoint: String = defaultHttpsEndpoint
  ): Result = withContext(Dispatchers.IO) {

    try {
      // --- Parse proxy ---
      val parts = proxyStr.split("@", limit = 2)
      val auth = if (parts.size == 2) parts[0] else null
      val hostPart = if (parts.size == 2) parts[1] else parts[0]

      // --- Parse auth safely ---
      val (username, password) = if (auth != null && auth.contains(":")) {
        val a = auth.split(":", limit = 2)
        a[0] to a.getOrNull(1)
      } else {
        null to null
      }

      // --- Parse host:port ---
      val hostParts = hostPart.split(":")
      if (hostParts.size < 2) {
        return@withContext Result(proxyStr,
          tcpOk = false,
          httpOk = false,
          httpsOk = false,
          latencyMs = 0,
          error = "INVALID_FORMAT"
        )
      }

      val host = hostParts[0]
      val port = hostParts[1].toIntOrNull()
        ?: return@withContext Result(proxyStr,
          tcpOk = false,
          httpOk = false,
          httpsOk = false,
          latencyMs = 0,
          error = "INVALID_PORT"
        )

      // --- TCP test ---
      val tcpOk = try {
        Socket().use { socket ->
          socket.connect(InetSocketAddress(host, port), timeoutMs)
        }
        true
      } catch (_: Exception) {
        false
      }

      if (!tcpOk) {
        return@withContext Result(proxyStr,
          tcpOk = false,
          httpOk = false,
          httpsOk = false,
          latencyMs = 0,
          error = "TCP_FAIL"
        )
      }

      val proxy = Proxy(Proxy.Type.HTTP, InetSocketAddress(host, port))
      val client = buildClient(proxy, username, password)

      // --- HTTP test ---
      val httpOk = try {
        val req = Request.Builder().url(httpEndpoint).build()
        client.newCall(req).execute().use { it.isSuccessful }
      } catch (_: Exception) {
        false
      }

      // --- HTTPS test (measure latency here) ---
      val httpsStart = System.currentTimeMillis()

      val httpsOk = try {
        val req = Request.Builder().url(httpsEndpoint).build()
        client.newCall(req).execute().use { it.isSuccessful }
      } catch (_: Exception) {
        false
      }

      val latency = System.currentTimeMillis() - httpsStart

      Result(proxyStr, true, httpOk, httpsOk, latency)

    } catch (e: Exception) {
      Result(
        proxy = proxyStr,
        tcpOk = false,
        httpOk = false,
        httpsOk = false,
        latencyMs = 0,
        error = classifyError(e)
      )
    }
  }

  /**
   * Batch scan (returns all results)
   */
  suspend fun scanProxies(
    proxies: List<String>,
    concurrency: Int = 100,
    httpEndpoint: String = defaultHttpEndpoint,
    httpsEndpoint: String = defaultHttpsEndpoint
  ): List<Result> = coroutineScope {

    val semaphore = Semaphore(concurrency)

    proxies.map { proxy ->
      async {
        semaphore.withPermit {
          check(proxy, httpEndpoint, httpsEndpoint)
        }
      }
    }.awaitAll()
  }

  /**
   * Streaming scan (BEST for Foreground Service / UI)
   */
  fun scanProxiesFlow(
    proxies: List<String>,
    concurrency: Int = 100,
    httpEndpoint: String = defaultHttpEndpoint,
    httpsEndpoint: String = defaultHttpsEndpoint,
    onStart: (suspend (String) -> Unit)? = null
  ): Flow<Result> = channelFlow {

    val semaphore = Semaphore(concurrency)

    proxies.forEach { proxy ->
      launch {
        semaphore.withPermit {
          onStart?.invoke(proxy)
          val result = check(proxy, httpEndpoint, httpsEndpoint)
          send(result)
        }
      }
    }
  }

  /**
   * Build OkHttp client
   */
  private fun buildClient(
    proxy: Proxy,
    username: String?,
    password: String?
  ): OkHttpClient {
    return OkHttpClient.Builder()
      .proxy(proxy)
      .connectionPool(connectionPool) // 🔥 performance boost
      .connectTimeout(timeoutMs.toLong(), TimeUnit.MILLISECONDS)
      .readTimeout(timeoutMs.toLong(), TimeUnit.MILLISECONDS)
      .callTimeout(timeoutMs.toLong(), TimeUnit.MILLISECONDS)
      .retryOnConnectionFailure(false)
      .proxyAuthenticator { _, response ->
        if (username != null && password != null) {
          val credential = Credentials.basic(username, password)
          response.request.newBuilder()
            .header("Proxy-Authorization", credential)
            .build()
        } else null
      }
      .build()
  }

  /**
   * Classify errors (VERY useful for filtering)
   */
  private fun classifyError(e: Exception): String {
    return when (e) {
      is SocketTimeoutException -> "TIMEOUT"
      is ConnectException -> "REFUSED"
      is UnknownHostException -> "DNS_FAIL"
      is NoRouteToHostException -> "NO_ROUTE"
      is SSLException -> "SSL_FAIL"
      else -> e.javaClass.simpleName
    }
  }
}
