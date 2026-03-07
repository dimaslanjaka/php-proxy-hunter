package com.dimaslanjaka.proxyhunter

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Column
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.Stop
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
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
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
import androidx.compose.ui.unit.dp
import com.dimaslanjaka.proxyhunter.checker.ProxyChecker
import com.dimaslanjaka.proxyhunter.data.ProxyItem
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.data.ProxyDB
import com.dimaslanjaka.proxyhunter.ui.theme.ProxyHunterTheme
import com.dimaslanjaka.utils.ProxyExtractor
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request

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

data class CheckResult(
    val proxyItem: ProxyItem,
    val checkerResult: ProxyChecker.CheckResult? = null,
    val isChecking: Boolean = false
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProxyCheckerScreen(onBack: () -> Unit, prefs: LocalSharedPrefs, db: ProxyDB) {
    val gson = remember { Gson() }
    var inputText by rememberSaveable { mutableStateOf(prefs.getString("last_input", "") ?: "") }
    val results = remember { mutableStateListOf<CheckResult>() }
    var isCheckingAll by rememberSaveable { mutableStateOf(false) }
    var isFetching by rememberSaveable { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    var checkJob by remember { mutableStateOf<Job?>(null) }

    // Restore results on first launch
    LaunchedEffect(Unit) {
        val savedResultsJson = prefs.getString("last_results", null)
        if (savedResultsJson != null) {
            try {
                val type = object : TypeToken<List<CheckResult>>() {}.type
                val savedResults: List<CheckResult> = gson.fromJson(savedResultsJson, type)
                results.addAll(savedResults)
            } catch (e: Exception) {
                e.printStackTrace()
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

            Row(modifier = Modifier.fillMaxWidth()) {
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
                                        e.printStackTrace()
                                    }
                                }
                            }

                            val extracted = ProxyExtractor.extract(allContent.toString())
                            val random100 = extracted.shuffled().take(100).joinToString("\n")
                            inputText = random100
                            prefs.put("last_input", random100)
                            isFetching = false
                        }
                    },
                    modifier = Modifier.weight(1f),
                    enabled = !isFetching && !isCheckingAll
                ) {
                    if (isFetching) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            strokeWidth = 2.dp,
                            color = LocalContentColor.current
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Fetching...")
                    } else {
                        Icon(Icons.Default.Download, contentDescription = null)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Fetch List")
                    }
                }

                Spacer(modifier = Modifier.width(8.dp))

                Button(
                    onClick = {
                        if (isCheckingAll) {
                            checkJob?.cancel()
                            isCheckingAll = false
                        } else {
                            results.clear()
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
                            results.addAll(proxies.map { CheckResult(it, null) })

                            checkJob?.cancel()
                            checkJob = scope.launch {
                                try {
                                    isCheckingAll = true
                                    proxies.forEachIndexed { index, proxyItem ->
                                        if (!isActive || results.isEmpty()) return@forEachIndexed

                                        if (index < results.size) {
                                            results[index] = results[index].copy(isChecking = true)
                                        }

                                        val checkerResult = withContext(Dispatchers.IO) {
                                            ProxyChecker.check(proxyItem)
                                        }

                                        if (isActive && index < results.size) {
                                            results[index] = results[index].copy(checkerResult = checkerResult, isChecking = false)

                                            // Record working proxy to DB
                                            if (checkerResult.isWorking && checkerResult.type != null) {
                                                withContext(Dispatchers.IO) {
                                                    try {
                                                        db.upsertProxy(
                                                            proxy = proxyItem.toString(),
                                                            type = checkerResult.type,
                                                            status = "active"
                                                        ).get()
                                                    } catch (e: Exception) {
                                                        e.printStackTrace()
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } finally {
                                    isCheckingAll = false
                                }
                            }
                        }
                    },
                    modifier = Modifier.weight(1f),
                    enabled = !isFetching && (isCheckingAll || inputText.isNotBlank()),
                    colors = if (isCheckingAll) ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error) else ButtonDefaults.buttonColors()
                ) {
                    if (isCheckingAll) {
                        Icon(
                            Icons.Default.Stop,
                            contentDescription = null,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Stop")
                    } else {
                        Text("Submit & Check")
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = "Results (${results.size})",
                    style = MaterialTheme.typography.titleMedium,
                    modifier = Modifier.weight(1f)
                )
                if (results.isNotEmpty()) {
                    OutlinedButton(
                        onClick = {
                            checkJob?.cancel()
                            results.clear()
                            isCheckingAll = false
                            prefs.put("last_results", "[]")
                        },
                        modifier = Modifier.height(32.dp),
                        contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                    ) {
                        Text("Clear", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }

            LazyColumn(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
            ) {
                items(results) { result ->
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
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(text = result.proxyItem.proxy, style = MaterialTheme.typography.bodyLarge)
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(top = 4.dp)) {
                val checker = result.checkerResult
                if (checker != null && checker.isWorking) {
                    val badgeColor = when {
                        checker.type?.lowercase()?.contains("socks5") == true -> Color(0xFF4CAF50)
                        checker.type?.lowercase()?.contains("socks4") == true -> Color(0xFFFF9800)
                        else -> Color(0xFF2196F3)
                    }
                    Surface(
                        color = badgeColor,
                        shape = RoundedCornerShape(4.dp),
                        modifier = Modifier.padding(end = 8.dp)
                    ) {
                        Text(
                            text = checker.type?.uppercase() ?: "UNKNOWN",
                            style = MaterialTheme.typography.labelSmall,
                            color = Color.White,
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                        )
                    }
                } else if (result.proxyItem.username != null && !result.isChecking && result.checkerResult == null) {
                    Text(
                        text = "Auth: ${result.proxyItem.username}:${result.proxyItem.password}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.Gray
                    )
                }
            }
        }

        when {
            result.isChecking -> {
                CircularProgressIndicator(modifier = Modifier.size(24.dp), strokeWidth = 2.dp)
            }
            result.checkerResult?.isWorking == true -> {
                Icon(Icons.Default.CheckCircle, contentDescription = "Working", tint = Color.Green)
            }
            result.checkerResult?.isWorking == false -> {
                Icon(Icons.Default.Error, contentDescription = "Failed", tint = Color.Red)
            }
        }
    }
}
