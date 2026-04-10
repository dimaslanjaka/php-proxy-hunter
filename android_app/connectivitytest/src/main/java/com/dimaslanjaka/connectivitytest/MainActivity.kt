package com.dimaslanjaka.connectivitytest

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Bundle
import android.webkit.WebViewClient
import android.webkit.WebView
import android.widget.LinearLayout
import android.widget.ProgressBar
import androidx.activity.ComponentActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.lifecycleScope
import androidx.lifecycle.repeatOnLifecycle
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.net.HttpURLConnection
import java.net.URL

class MainActivity : ComponentActivity() {
  private var testCompleted = false
  private lateinit var webView: WebView
  private lateinit var progressBar: ProgressBar

  object Contract {
    const val ACTION_RUN_CONNECTIVITY_TEST = "com.dimaslanjaka.connectivitytest.action.RUN_CONNECTIVITY_TEST"
    const val EXTRA_PROXY = "extra_proxy"
    const val EXTRA_SUCCESS = "extra_success"
    const val EXTRA_MESSAGE = "extra_message"
  }

  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)

    if (intent?.action == Contract.ACTION_RUN_CONNECTIVITY_TEST) {
      runConnectivityTestAndFinish()
      return
    }

    // Create a LinearLayout container
    val mainLayout = LinearLayout(this).apply {
      layoutParams = LinearLayout.LayoutParams(
        LinearLayout.LayoutParams.MATCH_PARENT,
        LinearLayout.LayoutParams.MATCH_PARENT
      )
      orientation = LinearLayout.VERTICAL
    }

    // Create and configure ProgressBar
    progressBar = ProgressBar(this, null, android.R.attr.progressBarStyle).apply {
      layoutParams = LinearLayout.LayoutParams(
        LinearLayout.LayoutParams.MATCH_PARENT,
        LinearLayout.LayoutParams.WRAP_CONTENT
      )
      isIndeterminate = true
      visibility = android.view.View.VISIBLE
    }

    // Create and configure WebView
    webView = WebView(this).apply {
      layoutParams = LinearLayout.LayoutParams(
        LinearLayout.LayoutParams.MATCH_PARENT,
        LinearLayout.LayoutParams.MATCH_PARENT,
        1f
      )
      settings.apply {
        javaScriptEnabled = true
        domStorageEnabled = true
        mixedContentMode = android.webkit.WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
      }
      webViewClient = object : WebViewClient() {
        override fun onPageStarted(view: WebView?, url: String?, favicon: android.graphics.Bitmap?) {
          super.onPageStarted(view, url, favicon)
          progressBar.visibility = android.view.View.VISIBLE
        }

        override fun onPageFinished(view: WebView?, url: String?) {
          super.onPageFinished(view, url)
          progressBar.visibility = android.view.View.GONE
        }
      }
    }

    // Add views to layout
    mainLayout.addView(progressBar)
    mainLayout.addView(webView)

    setContentView(mainLayout)

    // Apply edge-to-edge insets
    ViewCompat.setOnApplyWindowInsetsListener(mainLayout) { v, insets ->
      val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
      v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
      insets
    }

    // Load the WebView
    webView.loadUrl("http://sh.webmanajemen.com")
  }

  private fun runConnectivityTestAndFinish() {
    val mainLayout = LinearLayout(this).apply {
      layoutParams = LinearLayout.LayoutParams(
        LinearLayout.LayoutParams.MATCH_PARENT,
        LinearLayout.LayoutParams.MATCH_PARENT
      )
      orientation = LinearLayout.VERTICAL
    }

    val progressBar = ProgressBar(this, null, android.R.attr.progressBarStyle).apply {
      layoutParams = LinearLayout.LayoutParams(
        LinearLayout.LayoutParams.MATCH_PARENT,
        LinearLayout.LayoutParams.WRAP_CONTENT
      )
      isIndeterminate = true
    }

    val statusText = android.widget.TextView(this).apply {
      layoutParams = LinearLayout.LayoutParams(
        LinearLayout.LayoutParams.MATCH_PARENT,
        LinearLayout.LayoutParams.WRAP_CONTENT
      )
      text = "Checking connectivity..."
      textSize = 16f
      setPadding(16, 16, 16, 16)
    }

    mainLayout.addView(progressBar)
    mainLayout.addView(statusText)
    setContentView(mainLayout)

    lifecycleScope.launch {
      repeatOnLifecycle(Lifecycle.State.STARTED) {
        if (testCompleted) return@repeatOnLifecycle
        testCompleted = true

        val proxy = intent?.getStringExtra(Contract.EXTRA_PROXY).orEmpty()
        val (success, message) = withContext(Dispatchers.IO) { performConnectivityCheck(proxy) }
        setResult(
          Activity.RESULT_OK,
          Intent().apply {
            putExtra(Contract.EXTRA_SUCCESS, success)
            putExtra(Contract.EXTRA_MESSAGE, message)
            putExtra(Contract.EXTRA_PROXY, proxy)
          }
        )
        finish()
      }
    }
  }

  private fun performConnectivityCheck(proxy: String): Pair<Boolean, String> {
    val manager = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
    val network = manager.activeNetwork
    val capabilities = manager.getNetworkCapabilities(network)
    val hasValidatedInternet = capabilities?.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) == true &&
      capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)

    if (!hasValidatedInternet) {
      return false to "Connected to VPN, but no validated internet network"
    }

    val probeOk = runHttpProbe("https://clients3.google.com/generate_204") ||
      runHttpProbe("https://example.com")

    if (!probeOk) {
      return false to "VPN connected but failed to reach test endpoints"
    }

    val suffix = if (proxy.isBlank()) "" else " via $proxy"
    return true to "Connectivity test successful$suffix"
  }

  private fun runHttpProbe(urlValue: String): Boolean {
    return try {
      val connection = (URL(urlValue).openConnection() as HttpURLConnection).apply {
        requestMethod = "GET"
        connectTimeout = 5000
        readTimeout = 5000
        instanceFollowRedirects = true
      }
      connection.connect()
      val code = connection.responseCode
      connection.disconnect()
      code in 200..399
    } catch (_: Exception) {
      false
    }
  }
}

