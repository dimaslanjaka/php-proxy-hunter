package com.dimaslanjaka.proxyhunter.checker

import com.dimaslanjaka.proxyhunter.data.ProxyItem
import okhttp3.Authenticator
import okhttp3.Credentials
import okhttp3.OkHttpClient
import okhttp3.Request
import java.net.InetSocketAddress
import java.net.Proxy
import java.net.Socket
import java.util.concurrent.TimeUnit

object ProxyChecker {

  data class Options(
    val url: String = "https://www.google.com",
    val expectedTitle: String = "google",
    val timeoutSeconds: Long = 10
  )

  data class CheckResult(
    val isWorking: Boolean,
    val type: String? = null,
    val title: String? = null
  )

  private val baseClient = OkHttpClient.Builder()
    .connectTimeout(10, TimeUnit.SECONDS)
    .readTimeout(10, TimeUnit.SECONDS)
    .build()

  @JvmStatic
  fun check(
    proxyItem: ProxyItem,
    options: Options = Options()
  ): CheckResult {

    val host = proxyItem.host
    val port = proxyItem.port
    val timeout = (options.timeoutSeconds * 1000).toInt()

    val workingTypes = mutableListOf<String>()
    var lastTitle: String? = null

    val socks5 = if (!proxyItem.username.isNullOrBlank()) {
      isSocks5Auth(host, port, proxyItem.username, proxyItem.password ?: "", timeout)
    } else {
      isSocks5(host, port, timeout)
    }

    if (socks5) {
      val result = performSingleCheck(proxyItem, "socks5", options)
      if (result.isWorking) {
        workingTypes.add("socks5")
        lastTitle = result.title
      }
    }

    if (isSocks4(host, port, timeout)) {
      val result = performSingleCheck(proxyItem, "socks4", options)
      if (result.isWorking) {
        workingTypes.add("socks4")
        if (lastTitle == null) lastTitle = result.title
      }
    }

    val httpResult = performSingleCheck(proxyItem, "http", options)
    if (httpResult.isWorking) {
      workingTypes.add("http")
      if (lastTitle == null) lastTitle = httpResult.title
    }

    return if (workingTypes.isNotEmpty()) {
      CheckResult(true, workingTypes.joinToString("-"), lastTitle)
    } else {
      CheckResult(false)
    }
  }

  private fun performSingleCheck(
    proxyItem: ProxyItem,
    type: String,
    options: Options
  ): CheckResult {

    val proxyType = if (type == "http") Proxy.Type.HTTP else Proxy.Type.SOCKS

    val proxy = Proxy(
      proxyType,
      InetSocketAddress(proxyItem.host, proxyItem.port)
    )

    val builder = baseClient.newBuilder()
      .proxy(proxy)
      .connectTimeout(options.timeoutSeconds, TimeUnit.SECONDS)
      .readTimeout(options.timeoutSeconds, TimeUnit.SECONDS)

    if (!proxyItem.username.isNullOrBlank() && !proxyItem.password.isNullOrBlank()) {

      if (proxyType == Proxy.Type.HTTP) {

        val proxyAuthenticator = Authenticator { _, response ->
          val credential = Credentials.basic(
            proxyItem.username,
            proxyItem.password
          )

          response.request.newBuilder()
            .header("Proxy-Authorization", credential)
            .build()
        }

        builder.proxyAuthenticator(proxyAuthenticator)

      } else {

        System.setProperty("java.net.socks.username", proxyItem.username)
        System.setProperty("java.net.socks.password", proxyItem.password)
      }
    }

    val client = builder.build()

    val request = Request.Builder()
      .url(options.url)
      .header(
        "User-Agent",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
      )
      .header("Accept", "text/html,application/xhtml+xml")
      .header("Accept-Language", "en-US,en;q=0.9")
      .build()

    return try {

      client.newCall(request).execute().use { response ->

        if (!response.isSuccessful) {
          return CheckResult(false, type)
        }

        val body = response.body.string()

        val titleMatch = Regex(
          "<title[^>]*>(.*?)</title>",
          RegexOption.IGNORE_CASE
        ).find(body)

        val title = titleMatch?.groupValues?.get(1) ?: ""

        val isWorking = title.contains(
          options.expectedTitle,
          ignoreCase = true
        )

        CheckResult(
          isWorking,
          type,
          if (isWorking) title else null
        )
      }

    } catch (e: Exception) {
      CheckResult(false, type)
    }
  }

  private fun isSocks5(host: String, port: Int, timeout: Int): Boolean {

    return try {

      val socket = Socket()
      socket.connect(InetSocketAddress(host, port), timeout)

      val out = socket.getOutputStream()
      val input = socket.getInputStream()

      out.write(byteArrayOf(0x05, 0x01, 0x00))
      out.flush()

      val response = ByteArray(2)
      val read = input.read(response)

      socket.close()

      read == 2 && response[0].toInt() == 0x05

    } catch (e: Exception) {
      false
    }
  }

  private fun isSocks5Auth(
    host: String,
    port: Int,
    username: String,
    password: String,
    timeout: Int
  ): Boolean {

    return try {

      val socket = Socket()
      socket.connect(InetSocketAddress(host, port), timeout)

      val out = socket.getOutputStream()
      val input = socket.getInputStream()

      out.write(byteArrayOf(0x05, 0x01, 0x02))
      out.flush()

      val resp = ByteArray(2)
      input.read(resp)

      if (resp[1].toInt() != 0x02) {
        socket.close()
        return false
      }

      val userBytes = username.toByteArray()
      val passBytes = password.toByteArray()

      val authPacket = ByteArray(3 + userBytes.size + passBytes.size)

      authPacket[0] = 0x01
      authPacket[1] = userBytes.size.toByte()

      System.arraycopy(userBytes, 0, authPacket, 2, userBytes.size)

      authPacket[2 + userBytes.size] = passBytes.size.toByte()

      System.arraycopy(
        passBytes,
        0,
        authPacket,
        3 + userBytes.size,
        passBytes.size
      )

      out.write(authPacket)
      out.flush()

      val authResp = ByteArray(2)
      input.read(authResp)

      socket.close()

      authResp[1].toInt() == 0x00

    } catch (e: Exception) {
      false
    }
  }

  private fun isSocks4(host: String, port: Int, timeout: Int): Boolean {

    return try {

      val socket = Socket()
      socket.connect(InetSocketAddress(host, port), timeout)

      val out = socket.getOutputStream()
      val input = socket.getInputStream()

      val request = byteArrayOf(
        0x04,
        0x01,
        0x00,
        0x50,
        0x01,
        0x01,
        0x01,
        0x01,
        0x00
      )

      out.write(request)
      out.flush()

      val response = ByteArray(8)
      val read = input.read(response)

      socket.close()

      read >= 2 && response[1].toInt() == 0x5A

    } catch (e: Exception) {
      false
    }
  }
}
