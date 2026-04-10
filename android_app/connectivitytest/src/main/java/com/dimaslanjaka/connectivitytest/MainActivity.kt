package com.dimaslanjaka.connectivitytest

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.tooling.preview.Preview
import com.dimaslanjaka.connectivitytest.ui.theme.ProxyHunterTheme
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

    enableEdgeToEdge()
    setContent {
      ProxyHunterTheme {
        Scaffold(modifier = Modifier.fillMaxSize()) { innerPadding ->
          Greeting(
            name = "Android",
            modifier = Modifier.padding(innerPadding)
          )
        }
      }
    }
  }

  private fun runConnectivityTestAndFinish() {
    setContent {
      ProxyHunterTheme {
        Scaffold(modifier = Modifier.fillMaxSize()) { innerPadding ->
          Text(
            text = "Checking connectivity...",
            modifier = Modifier.padding(innerPadding)
          )
        }
      }
    }

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

@Composable
fun Greeting(name: String, modifier: Modifier = Modifier) {
  Text(
    text = "Hello $name!",
    modifier = modifier
  )
}

@Preview(showBackground = true)
@Composable
fun GreetingPreview() {
  ProxyHunterTheme {
    Greeting("Android")
  }
}
