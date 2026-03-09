package com.dimaslanjaka.proxyhunter

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.foundation.lazy.rememberLazyListState
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
import androidx.compose.material3.Checkbox
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
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import timber.log.Timber

data class CheckResult(
  val proxy: String,
  val checkerResult: ProxyChecker.CheckResult? = null,
  val isChecking: Boolean = false
)

class ProxyCheckerActivity : ComponentActivity() {
  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)
    ProxyManager.initialize(this)
    enableEdgeToEdge()
    window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

    setContent {
      ProxyHunterTheme {
        ProxyCheckerScreen(
          onBack = { finish() },
          prefs = ProxyManager.prefs,
          db = ProxyManager.db
        )
      }
    }
  }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProxyCheckerScreen(onBack: () -> Unit, prefs: LocalSharedPrefs, db: ProxyDB) {
  val context = LocalContext.current
  var inputText by rememberSaveable { mutableStateOf(prefs.getString("last_input", "") ?: "") }
  var limitInput by rememberSaveable { mutableStateOf(prefs.getString("limit_input", "50") ?: "50") }
  var autoCheckProxies by rememberSaveable { mutableStateOf(prefs.getBoolean("auto_check_proxies", false)) }
  var autoScrollResults by rememberSaveable { mutableStateOf(prefs.getBoolean("auto_scroll_results", true)) }
  val listState = rememberLazyListState()
  val results = remember { mutableStateListOf<CheckResult>() }

  val isCheckingAll by ProxyManager.isRunningFlow.collectAsState()
  val sessionResults by ProxyManager.resultsFlow.collectAsState()
  val currentProxy by ProxyManager.currentProxyFlow.collectAsState()

  var isFetching by rememberSaveable { mutableStateOf(false) }
  val scope = rememberCoroutineScope()

  // Load unique results from SQLite on launch
  fun loadLocalResults() {
    scope.launch(Dispatchers.IO) {
      try {
        // Query to get only the most recent result for each proxy to prevent list bloating
        val sql = "SELECT * FROM proxy_results WHERE id IN (SELECT MAX(id) FROM proxy_results GROUP BY proxy) ORDER BY timestamp DESC"
        val localResults = ProxyManager.localDb.query(sql).get()
        val mapped = localResults.map { row ->
          CheckResult(
            proxy = row["proxy"] as String,
            checkerResult = ProxyChecker.CheckResult(
              isWorking = (row["is_working"] as Long) == 1L,
              type = row["type"] as? String,
              title = row["title"] as? String
            )
          )
        }
        withContext(Dispatchers.Main) {
          results.clear()
          results.addAll(mapped)
        }
      } catch (e: Exception) {
        Timber.e(e, "Failed to load local results")
      }
    }
  }

  LaunchedEffect(Unit) {
    loadLocalResults()
  }

  // Auto-scroll to top when a new proxy starts checking
  LaunchedEffect(currentProxy) {
      if (autoScrollResults && currentProxy != null) {
          scope.launch {
              listState.animateScrollToItem(0)
          }
      }
  }

  // Real-time UI Sync with ProxyManager Flows
  LaunchedEffect(sessionResults, currentProxy) {
    // 1. Reset checking state for items no longer being checked
    for (i in results.indices) {
        if (results[i].isChecking && results[i].proxy != currentProxy) {
            results[i] = results[i].copy(isChecking = false)
        }
    }

    // 2. Sync session results (results found in the current run)
    sessionResults.forEach { (proxyStr, res) ->
      val index = results.indexOfFirst { it.proxy == proxyStr }
      if (index != -1) {
        // Update existing item and stop spinner
        if (results[index].checkerResult != res || results[index].isChecking) {
          results[index] = results[index].copy(checkerResult = res, isChecking = false)
        }
      } else {
        // Add new results to the top
        results.add(0, CheckResult(proxyStr, res, isChecking = false))
      }
    }

    // 3. Update the currently checking spinner
    if (currentProxy != null) {
        val index = results.indexOfFirst { it.proxy == currentProxy }
        if (index != -1) {
            if (!results[index].isChecking) {
                results[index] = results[index].copy(isChecking = true)
            }
        } else {
            results.add(0, CheckResult(currentProxy!!, isChecking = true))
        }
    }
  }

  // Refresh results when checking finishes to ensure everything is saved to DB
  LaunchedEffect(isCheckingAll) {
    if (!isCheckingAll) {
      loadLocalResults()
    }
  }

  // Broadcast Receiver to listen for real-time progress (Backup mechanism)
  DisposableEffect(context) {
    val receiver = object : BroadcastReceiver() {
      override fun onReceive(context: Context?, intent: Intent?) {
        when (intent?.action) {
          ProxyCheckService.ACTION_PROXY_CHECK_STARTED -> {
            val proxyStr = intent.getStringExtra(ProxyCheckService.EXTRA_PROXY) ?: ""
            if (proxyStr.isNotEmpty()) {
              val index = results.indexOfFirst { it.proxy == proxyStr }
              if (index != -1) {
                results[index] = results[index].copy(isChecking = true)
              } else {
                results.add(0, CheckResult(proxyStr, isChecking = true))
              }
            }
          }

          ProxyCheckService.ACTION_PROXY_CHECK_PROGRESS -> {
            val proxyStr = intent.getStringExtra(ProxyCheckService.EXTRA_PROXY) ?: ""
            val isWorking = intent.getBooleanExtra(ProxyCheckService.EXTRA_IS_WORKING, false)
            val type = intent.getStringExtra(ProxyCheckService.EXTRA_TYPE)
            val title = intent.getStringExtra(ProxyCheckService.EXTRA_TITLE)

            if (proxyStr.isNotEmpty()) {
              val index = results.indexOfFirst { it.proxy == proxyStr }
              val res = CheckResult(
                proxyStr,
                ProxyChecker.CheckResult(isWorking, type, title),
                isChecking = false
              )
              if (index != -1) {
                results[index] = res
              } else {
                results.add(0, res)
              }
            }
          }

          ProxyCheckService.ACTION_PROXY_CHECK_FINISHED -> {
             loadLocalResults()
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
              ProxyManager.clearResults()
              results.clear()
            },
            enabled = results.isNotEmpty() || isCheckingAll
          ) {
            Icon(Icons.Default.Delete, contentDescription = "Clear all results from database")
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

      // Checker Category
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .horizontalScroll(rememberScrollState()),
        verticalAlignment = Alignment.CenterVertically
      ) {
        // Auto Checking Proxy Checkbox inline
        Row(
          verticalAlignment = Alignment.CenterVertically,
          modifier = Modifier
            .clickable {
              val newValue = !autoCheckProxies
              autoCheckProxies = newValue
              prefs.put("auto_check_proxies", newValue)
              if (newValue && !isCheckingAll) {
                val serviceIntent = Intent(context, ProxyCheckService::class.java)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                  context.startForegroundService(serviceIntent)
                } else {
                  context.startService(serviceIntent)
                }
              }
            }
            .padding(end = 8.dp)
        ) {
          Checkbox(
            checked = autoCheckProxies,
            onCheckedChange = {
              autoCheckProxies = it
              prefs.put("auto_check_proxies", it)
              if (it && !isCheckingAll) {
                val serviceIntent = Intent(context, ProxyCheckService::class.java)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                  context.startForegroundService(serviceIntent)
                } else {
                  context.startService(serviceIntent)
                }
              }
            }
          )
          Text("Auto Checking Proxy", fontSize = 12.sp)
        }

        Spacer(modifier = Modifier.width(12.dp))

        Button(
          onClick = {
            if (isCheckingAll) {
              val serviceIntent = Intent(context, ProxyCheckService::class.java)
              context.stopService(serviceIntent)
              ProxyManager.setRunning(false)
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

              if (proxies.isNotEmpty()) {
                ProxyManager.set(proxies)
                val serviceIntent = Intent(context, ProxyCheckService::class.java).apply {
                  putExtra(ProxyCheckService.EXTRA_PRIORITY, true)
                }
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
          enabled = !isFetching && (isCheckingAll || inputText.isNotBlank()),
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

        Spacer(modifier = Modifier.width(12.dp))

        // Auto Scroll Checkbox moved to the right of "Check All" button
        Row(
          verticalAlignment = Alignment.CenterVertically,
          modifier = Modifier.clickable {
              val newValue = !autoScrollResults
              autoScrollResults = newValue
              prefs.put("auto_scroll_results", newValue)
            }.padding(end = 8.dp)
        ) {
          Checkbox(
            checked = autoScrollResults,
            onCheckedChange = {
              autoScrollResults = it
              prefs.put("auto_scroll_results", it)
            }
          )
          Text("Auto Scroll", fontSize = 12.sp)
        }
      }

      // Fetcher Category
      Row(
        modifier = Modifier
          .fillMaxWidth()
          .horizontalScroll(rememberScrollState()),
        verticalAlignment = Alignment.CenterVertically
      ) {
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

        Spacer(modifier = Modifier.width(12.dp))

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

      LazyColumn(
        state = listState,
        modifier = Modifier.fillMaxSize()
      ) {
        items(results, key = { it.proxy }) { result ->
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
        text = result.proxy,
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
