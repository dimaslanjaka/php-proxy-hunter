package com.dimaslanjaka.proxyhunter

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.animation.AnimatedVisibility
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
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
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
import com.dimaslanjaka.proxyhunter.service.ProxyCheckerServiceOptions
import com.dimaslanjaka.proxyhunter.service.ProxyCheckerServiceStarter
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import com.dimaslanjaka.proxyhunter.service.Tun2socksCompatibilityTestService
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
  val checkerResult: ProxyChecker.Result? = null,
  val isChecking: Boolean = false,
  val checkingService: String? = null
)

class ProxyCheckerActivity : ComponentActivity() {
  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)
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
  val serviceStarter = remember(context) { ProxyCheckerServiceStarter(context) }
  var inputText by rememberSaveable { mutableStateOf(prefs.getString("last_input", "") ?: "") }
  var limitInput by rememberSaveable { mutableStateOf(prefs.getString("limit_input", "50") ?: "50") }
  var autoCheckProxies by rememberSaveable { mutableStateOf(prefs.getBoolean("auto_check_proxies", false)) }
  var autoScrollResults by rememberSaveable { mutableStateOf(prefs.getBoolean("auto_scroll_results", true)) }
  var requireUDP by rememberSaveable { mutableStateOf(prefs.getBoolean("require_udp", true)) }
  var requireDNS by rememberSaveable { mutableStateOf(prefs.getBoolean("require_dns", true)) }
  var strictCheck by rememberSaveable { mutableStateOf(prefs.getBoolean("strict_check", true)) }
  var useTun2SocksService by rememberSaveable { mutableStateOf(prefs.getBoolean("use_tun2socks_service", true)) }
  var useProxyCheckService by rememberSaveable { mutableStateOf(prefs.getBoolean("use_proxy_check_service", true)) }
  var useCheckerHttpService by rememberSaveable { mutableStateOf(prefs.getBoolean("use_checker_http_service", false)) }
  var inputSectionExpanded by rememberSaveable {
    mutableStateOf(prefs.getBoolean("input_section_expanded", true))
  }
  var optionsSectionExpanded by rememberSaveable {
    mutableStateOf(prefs.getBoolean("options_section_expanded", true))
  }

  val listState = rememberLazyListState()
  val results = remember { mutableStateListOf<CheckResult>() }

  val isCheckingAll by ProxyManager.isRunningFlow.collectAsState()
  val sessionResults by ProxyManager.resultsFlow.collectAsState()
  val checkingProxies by ProxyManager.checkingProxiesFlow.collectAsState()

  var isFetching by rememberSaveable { mutableStateOf(false) }
  val scope = rememberCoroutineScope()

  fun currentServiceOptions(): ProxyCheckerServiceOptions {
    return ProxyCheckerServiceOptions(
      useProxyCheckService = useProxyCheckService,
      useTun2SocksService = useTun2SocksService,
      useCheckerHttpService = useCheckerHttpService
    )
  }

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
            checkerResult = ProxyChecker.Result(
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
  LaunchedEffect(checkingProxies) {
      if (autoScrollResults && checkingProxies.isNotEmpty()) {
          scope.launch {
              listState.animateScrollToItem(0)
          }
      }
  }

  // Real-time UI Sync with ProxyManager Flows
  LaunchedEffect(sessionResults, checkingProxies) {
    // 1. Reset checking state and sync current checking services
    for (i in results.indices) {
        val proxy = results[i].proxy
        val service = checkingProxies[proxy]
        if (service != null) {
            if (!results[i].isChecking || results[i].checkingService != service) {
                results[i] = results[i].copy(isChecking = true, checkingService = service)
            }
        } else if (results[i].isChecking) {
            results[i] = results[i].copy(isChecking = false, checkingService = null)
        }
    }

    // 2. Sync session results (results found in the current run)
    sessionResults.forEach { (proxyStr, res) ->
      val index = results.indexOfFirst { it.proxy == proxyStr }
      if (index != -1) {
        // Update existing item and stop spinner if not checking by another service
        if (results[index].checkerResult != res || (results[index].isChecking && !checkingProxies.containsKey(proxyStr))) {
          results[index] = results[index].copy(
              checkerResult = res as? ProxyChecker.Result ?: (res as? com.dimaslanjaka.proxyhunter.checker.CheckerHttp.Result)?.let {
                  ProxyChecker.Result(it.httpOk || it.httpsOk, if (it.httpsOk) "https" else if (it.httpOk) "http" else null, it.error)
              },
              isChecking = checkingProxies.containsKey(proxyStr),
              checkingService = checkingProxies[proxyStr]
          )
        }
      } else {
        // Add new results to the top
        val mappedRes = res as? ProxyChecker.Result ?: (res as? com.dimaslanjaka.proxyhunter.checker.CheckerHttp.Result)?.let {
            ProxyChecker.Result(it.httpOk || it.httpsOk, if (it.httpsOk) "https" else if (it.httpOk) "http" else null, it.error)
        }
        results.add(0, CheckResult(proxyStr, mappedRes, isChecking = checkingProxies.containsKey(proxyStr), checkingService = checkingProxies[proxyStr]))
      }
    }

    // 3. Add new proxies that started checking but have no results yet
    checkingProxies.forEach { (proxy, service) ->
        if (results.none { it.proxy == proxy }) {
            results.add(0, CheckResult(proxy, isChecking = true, checkingService = service))
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
        val action = intent?.action ?: return
        when (action) {
          ProxyCheckService.ACTION_PROXY_CHECK_STARTED,
          Tun2socksCompatibilityTestService.ACTION_COMPATIBILITY_CHECK_STARTED -> {
            val proxyStr = intent.getStringExtra(ProxyCheckService.EXTRA_PROXY) ?:
                           intent.getStringExtra(Tun2socksCompatibilityTestService.EXTRA_PROXY) ?: ""
            val serviceName = if (action == ProxyCheckService.ACTION_PROXY_CHECK_STARTED) "ProxyCheckService" else "Tun2socksCompatibilityTestService"
            if (proxyStr.isNotEmpty()) {
              val index = results.indexOfFirst { it.proxy == proxyStr }
              if (index != -1) {
                results[index] = results[index].copy(isChecking = true, checkingService = serviceName)
              } else {
                results.add(0, CheckResult(proxyStr, isChecking = true, checkingService = serviceName))
              }
            }
          }

          ProxyCheckService.ACTION_PROXY_CHECK_PROGRESS,
          Tun2socksCompatibilityTestService.ACTION_COMPATIBILITY_CHECK_PROGRESS -> {
            val proxyStr = intent.getStringExtra(ProxyCheckService.EXTRA_PROXY) ?:
                           intent.getStringExtra(Tun2socksCompatibilityTestService.EXTRA_PROXY) ?: ""
            val isWorking = intent.getBooleanExtra(ProxyCheckService.EXTRA_IS_WORKING, false)
            val type = intent.getStringExtra(ProxyCheckService.EXTRA_TYPE) ?:
                       intent.getStringExtra(Tun2socksCompatibilityTestService.EXTRA_TYPE)
            val title = intent.getStringExtra(ProxyCheckService.EXTRA_TITLE) ?:
                        intent.getStringExtra(Tun2socksCompatibilityTestService.EXTRA_TITLE)

            if (proxyStr.isNotEmpty()) {
              val index = results.indexOfFirst { it.proxy == proxyStr }
              val res = CheckResult(
                proxyStr,
                ProxyChecker.Result(isWorking, type, title),
                isChecking = checkingProxies.containsKey(proxyStr),
                checkingService = checkingProxies[proxyStr]
              )
              if (index != -1) {
                results[index] = res
              } else {
                results.add(0, res)
              }
            }
          }

          ProxyCheckService.ACTION_PROXY_CHECK_FINISHED,
          Tun2socksCompatibilityTestService.ACTION_COMPATIBILITY_CHECK_FINISHED -> {
             loadLocalResults()
          }
        }
      }
    }
    val filter = IntentFilter().apply {
      addAction(ProxyCheckService.ACTION_PROXY_CHECK_STARTED)
      addAction(ProxyCheckService.ACTION_PROXY_CHECK_PROGRESS)
      addAction(ProxyCheckService.ACTION_PROXY_CHECK_FINISHED)
      addAction(Tun2socksCompatibilityTestService.ACTION_COMPATIBILITY_CHECK_STARTED)
      addAction(Tun2socksCompatibilityTestService.ACTION_COMPATIBILITY_CHECK_PROGRESS)
      addAction(Tun2socksCompatibilityTestService.ACTION_COMPATIBILITY_CHECK_FINISHED)
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
                serviceStarter.stopAll()
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
      CollapsibleSection(
        title = "Proxy Input",
        expanded = inputSectionExpanded,
        onExpandedChange = {
          inputSectionExpanded = it
          prefs.put("input_section_expanded", it)
        }
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

        Spacer(modifier = Modifier.height(12.dp))

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
      }

      Spacer(modifier = Modifier.height(8.dp))

      CollapsibleSection(
        title = "Checker Options",
        expanded = optionsSectionExpanded,
        onExpandedChange = {
          optionsSectionExpanded = it
          prefs.put("options_section_expanded", it)
        }
      ) {
        Column(
          modifier = Modifier.fillMaxWidth()
        ) {
          Row(
            modifier = Modifier
              .fillMaxWidth()
              .horizontalScroll(rememberScrollState()),
            verticalAlignment = Alignment.CenterVertically
          ) {
            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable {
                  val newValue = !autoCheckProxies
                  autoCheckProxies = newValue
                  prefs.put("auto_check_proxies", newValue)
                  if (newValue && !isCheckingAll) {
                    serviceStarter.start(currentServiceOptions())
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
                    serviceStarter.start(currentServiceOptions())
                  }
                }
              )
              Text("Auto Check", fontSize = 12.sp)
            }

            Spacer(modifier = Modifier.width(4.dp))

            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable {
                  val newValue = !useProxyCheckService
                  useProxyCheckService = newValue
                  prefs.put("use_proxy_check_service", newValue)
                }
                .padding(end = 8.dp)
            ) {
              Checkbox(
                checked = useProxyCheckService,
                onCheckedChange = {
                  useProxyCheckService = it
                  prefs.put("use_proxy_check_service", it)
                }
              )
              Text("Standard", fontSize = 12.sp)
            }

            Spacer(modifier = Modifier.width(4.dp))

            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable {
                  val newValue = !useTun2SocksService
                  useTun2SocksService = newValue
                  prefs.put("use_tun2socks_service", newValue)
                }
                .padding(end = 8.dp)
            ) {
              Checkbox(
                checked = useTun2SocksService,
                onCheckedChange = {
                  useTun2SocksService = it
                  prefs.put("use_tun2socks_service", it)
                }
              )
              Text("Tun2Socks", fontSize = 12.sp)
            }

            Spacer(modifier = Modifier.width(4.dp))

            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable {
                  val newValue = !useCheckerHttpService
                  useCheckerHttpService = newValue
                  prefs.put("use_checker_http_service", newValue)
                }
                .padding(end = 8.dp)
            ) {
              Checkbox(
                checked = useCheckerHttpService,
                onCheckedChange = {
                  useCheckerHttpService = it
                  prefs.put("use_checker_http_service", it)
                }
              )
              Text("HTTP Check", fontSize = 12.sp)
            }
          }

          Row(
            modifier = Modifier
              .fillMaxWidth()
              .horizontalScroll(rememberScrollState()),
            verticalAlignment = Alignment.CenterVertically
          ) {
            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable {
                  val newValue = !strictCheck
                  strictCheck = newValue
                  prefs.put("strict_check", newValue)
                }
                .padding(end = 8.dp)
            ) {
              Checkbox(
                checked = strictCheck,
                onCheckedChange = {
                  strictCheck = it
                  prefs.put("strict_check", it)
                }
              )
              Text("Strict Check", fontSize = 12.sp)
            }

            Spacer(modifier = Modifier.width(8.dp))

            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable(enabled = strictCheck) {
                  val newValue = !requireUDP
                  requireUDP = newValue
                  prefs.put("require_udp", newValue)
                }
                .padding(end = 8.dp)
            ) {
              Checkbox(
                checked = requireUDP,
                onCheckedChange = {
                  requireUDP = it
                  prefs.put("require_udp", it)
                },
                enabled = strictCheck
              )
              Text("Require UDP", fontSize = 12.sp, color = if (strictCheck) Color.Unspecified else Color.Gray)
            }

            Spacer(modifier = Modifier.width(8.dp))

            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable(enabled = strictCheck) {
                  val newValue = !requireDNS
                  requireDNS = newValue
                  prefs.put("require_dns", newValue)
                }
                .padding(end = 8.dp)
            ) {
              Checkbox(
                checked = requireDNS,
                onCheckedChange = {
                  requireDNS = it
                  prefs.put("require_dns", it)
                },
                enabled = strictCheck
              )
              Text("Require DNS", fontSize = 12.sp, color = if (strictCheck) Color.Unspecified else Color.Gray)
            }
          }

          Row(
            modifier = Modifier
              .fillMaxWidth()
              .horizontalScroll(rememberScrollState()),
            verticalAlignment = Alignment.CenterVertically
          ) {
            Button(
              onClick = {
                if (isCheckingAll) {
                  serviceStarter.stopAll()
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
                    serviceStarter.start(
                      options = currentServiceOptions(),
                      proxies = proxies,
                      isPriority = true
                    )
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

            Row(
              verticalAlignment = Alignment.CenterVertically,
              modifier = Modifier
                .clickable {
                  val newValue = !autoScrollResults
                  autoScrollResults = newValue
                  prefs.put("auto_scroll_results", newValue)
                }
                .padding(end = 8.dp)
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
        }
      }

      Spacer(modifier = Modifier.height(12.dp))

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
fun CollapsibleSection(
  title: String,
  expanded: Boolean,
  onExpandedChange: (Boolean) -> Unit,
  content: @Composable () -> Unit
) {
  Column(modifier = Modifier.fillMaxWidth()) {
    Row(
      modifier = Modifier
        .fillMaxWidth()
        .clickable { onExpandedChange(!expanded) }
        .padding(vertical = 6.dp),
      verticalAlignment = Alignment.CenterVertically
    ) {
      Text(
        text = title,
        style = MaterialTheme.typography.titleSmall,
        modifier = Modifier.weight(1f)
      )
      Icon(
        imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
        contentDescription = if (expanded) "Collapse $title" else "Expand $title"
      )
    }

    AnimatedVisibility(visible = expanded) {
      Column {
        content()
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
      val indicatorColor = when (result.checkingService) {
          "Tun2socksCompatibilityTestService" -> Color.Yellow
          "ProxyCheckService" -> Color.Blue
          "CheckerHttpService" -> Color(0xFF8E24AA) // Purple
          else -> MaterialTheme.colorScheme.primary
      }
      CircularProgressIndicator(
        modifier = Modifier.size(24.dp),
        strokeWidth = 3.dp,
        color = indicatorColor
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
