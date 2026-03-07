package com.dimaslanjaka.proxyhunter

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material.icons.filled.Storage
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import com.dimaslanjaka.proxyhunter.data.ProxyDB
import com.dimaslanjaka.proxyhunter.data.ProxyItem
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import com.dimaslanjaka.proxyhunter.ui.theme.ProxyHunterTheme
import com.dimaslanjaka.utils.ProxyExtractor
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import timber.log.Timber

data class CheckResult(
  val proxyItem: ProxyItem,
  val checkerResult: ProxyChecker.CheckResult? = null,
  val isChecking: Boolean = false
)

class ProxyCheckerActivity : ComponentActivity() {
  private lateinit var prefs: LocalSharedPrefs
  private lateinit var db: ProxyDB

  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)
    prefs = LocalSharedPrefs.initialize(this, "proxy_checker_prefs")
    db = ProxyDB()
    enableEdgeToEdge()
    setContent {
      ProxyHunterTheme {
        ProxyCheckerScreen(
          onBack = { finish() },
          prefs = prefs,
          db = db
        )
      }
    }
  }

  override fun onDestroy() {
    super.onDestroy()
    if (::db.isInitialized) {
      db.close()
    }
  }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProxyCheckerScreen(onBack: () -> Unit, prefs: LocalSharedPrefs, db: ProxyDB) {
  val context = LocalContext.current
  val gson = remember { Gson() }
  var inputText by rememberSaveable { mutableStateOf(prefs.getString("last_input", "") ?: "") }
  var limitInput by rememberSaveable { mutableStateOf(prefs.getString("limit_input", "50") ?: "50") }
  val results = remember { mutableStateListOf<CheckResult>() }

  // Use StateFlow from ProxyManager for reliable service status
  val isCheckingAll by ProxyManager.isRunningFlow.collectAsState()
  var isFetching by rememberSaveable { mutableStateOf(false) }
  val scope = rememberCoroutineScope()

  val managerResults by ProxyManager.resultsFlow.collectAsState()

  // Sync results with ProxyManager Flow
  LaunchedEffect(managerResults) {
    managerResults.forEach { (proxyStr, res) ->
      val index = results.indexOfFirst { it.proxyItem.toString() == proxyStr }
      if (index != -1) {
        if (results[index].checkerResult != res || results[index].isChecking) {
          results[index] = results[index].copy(checkerResult = res, isChecking = false)
        }
      }
    }
  }

  // Broadcast Receiver to listen for status changes
  DisposableEffect(context) {
    val receiver = object : BroadcastReceiver() {
      override fun onReceive(context: Context?, intent: Intent?) {
        when (intent?.action) {
          ProxyCheckService.ACTION_PROXY_CHECK_STARTED -> {
            val proxyStr = intent.getStringExtra(ProxyCheckService.EXTRA_PROXY) ?: ""
            if (proxyStr.isNotEmpty()) {
              val index = results.indexOfFirst { it.proxyItem.toString() == proxyStr }
              if (index != -1) {
                results[index] = results[index].copy(isChecking = true)
              }
            }
          }

          ProxyCheckService.ACTION_PROXY_CHECK_PROGRESS -> {
            val proxyStr = intent.getStringExtra(ProxyCheckService.EXTRA_PROXY) ?: ""
            val isWorking = intent.getBooleanExtra(ProxyCheckService.EXTRA_IS_WORKING, false)
            val type = intent.getStringExtra(ProxyCheckService.EXTRA_TYPE)
            val title = intent.getStringExtra(ProxyCheckService.EXTRA_TITLE)

            if (proxyStr.isNotEmpty()) {
              val index = results.indexOfFirst { it.proxyItem.toString() == proxyStr }
              if (index != -1) {
                results[index] = results[index].copy(
                  checkerResult = ProxyChecker.CheckResult(isWorking, type, title),
                  isChecking = false
                )
              }
            }
          }

          ProxyCheckService.ACTION_PROXY_CHECK_FINISHED -> {
            for (i in results.indices) {
              if (results[i].isChecking) {
                results[i] = results[i].copy(isChecking = false)
              }
            }
          }
        }
      }
    }
    val filter = IntentFilter().apply {
      addAction(ProxyCheckService.ACTION_PROXY_CHECK_STARTED)
      addAction(ProxyCheckService.ACTION_PROXY_CHECK_PROGRESS)
      addAction(ProxyCheckService.ACTION_PROXY_CHECK_FINISHED)
    }

    ContextCompat.registerReceiver(
      context,
      receiver,
      filter,
      ContextCompat.RECEIVER_NOT_EXPORTED
    )

    onDispose {
      context.unregisterReceiver(receiver)
    }
  }

  // Restore results on first launch and sync with running service
  LaunchedEffect(Unit) {
    val savedResultsJson = prefs.getString("last_results", null)
    if (!savedResultsJson.isNullOrBlank()) {
      try {
        val type = object : TypeToken<List<CheckResult>>() {}.type
        val savedResults: List<CheckResult> = gson.fromJson(savedResultsJson, type)
        results.clear()
        results.addAll(savedResults.map { it.copy(isChecking = false) })
      } catch (e: Exception) {
        Timber.e(e, "Failed to restore results")
      }
    }

    if (isCheckingAll) {
      val current = ProxyManager.currentProxyFlow.value
      val queue = ProxyManager.get().map { it.toString() }.toSet()
      for (i in results.indices) {
        val proxyStr = results[i].proxyItem.toString()
        if (proxyStr == current || (queue.contains(proxyStr) && results[i].checkerResult == null)) {
          results[i] = results[i].copy(isChecking = true)
        }
      }
    }
  }

  // Save results whenever they change and checking is finished
  LaunchedEffect(results.toList(), isCheckingAll) {
    if (!isCheckingAll && results.isNotEmpty()) {
      val json = gson.toJson(results.toList())
      prefs.put("last_results", json)
    }
  }

  Scaffold(
    topBar = {
      TopAppBar(
        title = { Text("Proxy Checker") },
        navigationIcon = {
          IconButton(onClick = onBack) {
            Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
          }
        },
        actions = {
          IconButton(
            onClick = {
              if (isCheckingAll) {
                val serviceIntent = Intent(context, ProxyCheckService::class.java)
                context.stopService(serviceIntent)
                ProxyManager.setRunning(false)
              }
              results.clear()
              prefs.put("last_results", "")
            },
            enabled = results.isNotEmpty() || isCheckingAll
          ) {
            Icon(Icons.Default.Delete, contentDescription = "Clear all")
          }
        }
      )
    }
  ) { innerPadding ->
    Column(
      modifier = Modifier
        .fillMaxSize()
        .padding(innerPadding)
        .padding(16.dp)
    ) {
      OutlinedTextField(
        value = inputText,
        onValueChange = {
          inputText = it
          prefs.put("last_input", it)
        },
        label = { Text("Enter proxies (host:port or user:pass@host:port)") },
        modifier = Modifier
          .fillMaxWidth()
          .height(150.dp),
        placeholder = { Text("127.0.0.1:8080\nuser:pass@1.2.3.4:1080") }
      )

      Spacer(modifier = Modifier.height(16.dp))

      Row(
        modifier = Modifier
          .fillMaxWidth()
          .horizontalScroll(rememberScrollState()),
        verticalAlignment = Alignment.CenterVertically
      ) {
        Button(
          onClick = {
            if (isCheckingAll) {
              val serviceIntent = Intent(context, ProxyCheckService::class.java)
              context.stopService(serviceIntent)
              ProxyManager.setRunning(false)
              // Broadcast receiver will handle setting isChecking to false for all items
            } else {
              val extractedStrings = ProxyExtractor.extract(inputText)
              val proxies = extractedStrings.map { raw ->
                if (raw.contains("@")) {
                  val parts = raw.split("@")
                  val auth = parts[0].split(":")
                  val hostPort = parts[1]
                  ProxyItem(
                    proxy = hostPort,
                    username = auth.getOrNull(0),
                    password = auth.getOrNull(1)
                  )
                } else {
                  ProxyItem(proxy = raw)
                }
              }

              proxies.forEach { proxyItem ->
                val proxyStr = proxyItem.toString()
                if (results.none { it.proxyItem.toString() == proxyStr }) {
                  results.add(CheckResult(proxyItem, null))
                }
              }

              val unfinished = results.filter { it.checkerResult == null }
              if (unfinished.isNotEmpty()) {
                ProxyManager.set(unfinished.map { it.proxyItem })

                for (u in unfinished) {
                  val idx = results.indexOfFirst { it.proxyItem.toString() == u.proxyItem.toString() }
                  if (idx != -1) results[idx] = results[idx].copy(isChecking = true)
                }

                val serviceIntent = Intent(context, ProxyCheckService::class.java)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                  context.startForegroundService(serviceIntent)
                } else {
                  context.startService(serviceIntent)
                }
              }
            }
          },
          modifier = Modifier.height(36.dp),
          contentPadding = PaddingValues(horizontal = 12.dp, vertical = 0.dp),
          enabled = !isFetching && (isCheckingAll || inputText.isNotBlank() || results.any { it.checkerResult == null }),
          colors = if (isCheckingAll) ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error) else ButtonDefaults.buttonColors()
        ) {
          if (isCheckingAll) {
            Icon(Icons.Default.Stop, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text("Stop", fontSize = 12.sp)
          } else {
            Icon(Icons.Default.PlayArrow, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text("Check All", fontSize = 12.sp)
          }
        }

        Spacer(modifier = Modifier.width(8.dp))

        OutlinedTextField(
          value = limitInput,
          onValueChange = {
            if (it.isEmpty()) {
              limitInput = it
              prefs.put("limit_input", it)
            } else if (it.all { char -> char.isDigit() }) {
              val num = it.toLongOrNull() ?: 0
              if (num <= 1000) {
                limitInput = it
                prefs.put("limit_input", it)
              }
            }
          },
          label = { Text("Limit", fontSize = 10.sp) },
          modifier = Modifier.width(80.dp),
          keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
          singleLine = true,
          textStyle = MaterialTheme.typography.bodyMedium.copy(fontSize = 16.sp)
        )

        Spacer(modifier = Modifier.width(8.dp))

        OutlinedButton(
          onClick = {
            scope.launch {
              isFetching = true
              val urls = listOf(
                "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/refs/heads/master/socks5.txt",
                "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/refs/heads/master/socks4.txt",
                "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/refs/heads/master/http.txt"
              )
              val client = OkHttpClient()
              val allContent = StringBuilder()

              withContext(Dispatchers.IO) {
                urls.forEach { url ->
                  try {
                    val request = Request.Builder().url(url).build()
                    client.newCall(request).execute().use { response ->
                      if (response.isSuccessful) {
                        response.body.let { body ->
                          allContent.append(body.string()).append("\n")
                        }
                      }
                    }
                  } catch (e: Exception) {
                    Timber.e(e, "Fetch failed for $url")
                  }
                }
              }

              val extracted = ProxyExtractor.extract(allContent.toString())
              val limit = limitInput.toIntOrNull() ?: 10
              val randomProxyList = extracted.shuffled().take(limit).joinToString("\n")
              inputText = randomProxyList
              prefs.put("last_input", randomProxyList)
              isFetching = false
            }
          },
          modifier = Modifier.height(36.dp),
          contentPadding = PaddingValues(horizontal = 12.dp, vertical = 0.dp),
          enabled = !isFetching && !isCheckingAll
        ) {
          if (isFetching) {
            CircularProgressIndicator(
              modifier = Modifier.size(18.dp),
              strokeWidth = 2.dp,
              color = LocalContentColor.current
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text("Fetching...", fontSize = 12.sp)
          } else {
            Icon(Icons.Default.Download, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text("Fetch List", fontSize = 12.sp)
          }
        }

        Spacer(modifier = Modifier.width(8.dp))

        OutlinedButton(
          onClick = {
            scope.launch {
              isFetching = true
              val limit = limitInput.toIntOrNull() ?: 50
              val untested = withContext(Dispatchers.IO) {
                db.getUntestedProxies(limit).get()
              }
              val untestedStr = untested.joinToString("\n") { it.toString() }
              inputText = untestedStr
              prefs.put("last_input", untestedStr)
              isFetching = false
            }
          },
          modifier = Modifier.height(36.dp),
          contentPadding = PaddingValues(horizontal = 12.dp, vertical = 0.dp),
          enabled = !isFetching && !isCheckingAll
        ) {
          if (isFetching) {
            CircularProgressIndicator(
              modifier = Modifier.size(18.dp),
              strokeWidth = 2.dp,
              color = LocalContentColor.current
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text("Loading...", fontSize = 12.sp)
          } else {
            Icon(Icons.Default.Storage, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text("From DB", fontSize = 12.sp)
          }
        }
      }

      Spacer(modifier = Modifier.height(16.dp))

      LazyColumn(modifier = Modifier.fillMaxSize()) {
        items(results, key = { it.proxyItem.toString() }) { result ->
          ProxyResultItem(result)
          HorizontalDivider()
        }
      }
    }
  }
}

@Composable
fun ProxyResultItem(result: CheckResult) {
  Row(
    modifier = Modifier
      .fillMaxWidth()
      .padding(vertical = 12.dp, horizontal = 4.dp),
    verticalAlignment = Alignment.CenterVertically
  ) {
    Column(modifier = Modifier.weight(1f)) {
      Text(
        text = result.proxyItem.toString(),
        style = MaterialTheme.typography.bodyLarge.copy(
          fontWeight = FontWeight.Bold,
          fontSize = 16.sp
        ),
        maxLines = 1,
        overflow = TextOverflow.Ellipsis
      )

      Row(
        modifier = Modifier.padding(top = 6.dp),
        verticalAlignment = Alignment.CenterVertically
      ) {
        if (result.checkerResult != null) {
          val isWorking = result.checkerResult.isWorking
          val backgroundColor = if (isWorking) Color(0xFF4CAF50) else Color(0xFFE53935)

          if (isWorking) {
            Box(
              modifier = Modifier
                .background(color = backgroundColor, shape = RoundedCornerShape(4.dp))
                .padding(horizontal = 8.dp, vertical = 2.dp)
            ) {
              Text(
                text = result.checkerResult.type?.uppercase() ?: "WORKING",
                color = Color.White,
                fontSize = 11.sp,
                fontWeight = FontWeight.ExtraBold
              )
            }
          }

          if (isWorking && !result.checkerResult.title.isNullOrBlank()) {
            Spacer(modifier = Modifier.width(8.dp))
            Text(
              text = result.checkerResult.title,
              style = MaterialTheme.typography.bodySmall,
              color = Color.Gray,
              maxLines = 1,
              overflow = TextOverflow.Ellipsis
            )
          }
        }
      }
    }

    Spacer(modifier = Modifier.width(16.dp))

    if (result.isChecking) {
      CircularProgressIndicator(
        modifier = Modifier.size(24.dp),
        strokeWidth = 3.dp,
        color = MaterialTheme.colorScheme.primary
      )
    } else if (result.checkerResult != null) {
      Icon(
        imageVector = if (result.checkerResult.isWorking) Icons.Default.CheckCircle else Icons.Default.Error,
        contentDescription = null,
        tint = if (result.checkerResult.isWorking) Color(0xFF4CAF50) else Color(0xFFE53935),
        modifier = Modifier.size(28.dp)
      )
    }
  }
}
