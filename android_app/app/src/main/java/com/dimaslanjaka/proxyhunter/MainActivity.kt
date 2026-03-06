package com.dimaslanjaka.proxyhunter

import android.content.Intent
import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import com.dimaslanjaka.proxyhunter.data.ProxyDB
import com.dimaslanjaka.proxyhunter.data.ProxyItem
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import com.dimaslanjaka.proxyhunter.ui.theme.ProxyHunterTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.*

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Initial background fetch to start the checker service for untested proxies
        fetchUntestedAndStartService()

        enableEdgeToEdge()
        setContent {
            ProxyHunterTheme {
                ProxyListScreen()
            }
        }
    }

    private fun fetchUntestedAndStartService() {
        val proxyDB = ProxyDB()
        Thread {
            try {
                Log.d("ProxyHunter", "Checking for untested proxies to start service...")
                val proxies = proxyDB.getUntestedProxies(limit = 100).get()
                if (proxies.isNotEmpty()) {
                    Log.d("ProxyHunter", "Found ${proxies.size} untested proxies. Starting service.")
                    ProxyManager.set(proxies)
                    val intent = Intent(this, ProxyCheckService::class.java)
                    ContextCompat.startForegroundService(this, intent)
                }
            } catch (e: Exception) {
                Log.e("ProxyHunter", "Service initialization failed", e)
            } finally {
                proxyDB.close()
            }
        }.start()
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProxyListScreen() {
    val scope = rememberCoroutineScope()
    var proxies by remember { mutableStateOf(listOf<ProxyItem>()) }
    var isLoading by remember { mutableStateOf(false) }

    val refreshProxies = {
        scope.launch {
            isLoading = true
            val db = ProxyDB()
            try {
                val result = withContext(Dispatchers.IO) {
                    // Fetch active proxies from database
                    db.getWorkingProxies(limit = 50).get()
                }
                proxies = result
            } catch (e: Exception) {
                Log.e("ProxyHunter", "UI Refresh failed", e)
            } finally {
                db.close()
                isLoading = false
            }
        }
    }

    // Initial load when screen opens
    LaunchedEffect(Unit) {
        refreshProxies()
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Working Proxies") },
                actions = {
                    IconButton(onClick = { refreshProxies() }, enabled = !isLoading) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                }
            )
        }
    ) { innerPadding ->
        Box(modifier = Modifier.padding(innerPadding).fillMaxSize()) {
            if (isLoading && proxies.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.Center))
            } else if (proxies.isEmpty()) {
                Text("No active proxies found in database.", modifier = Modifier.align(Alignment.Center))
            } else {
                LazyColumn {
                    items(proxies) { proxy ->
                        ProxyRow(proxy)
                        HorizontalDivider(color = Color.LightGray, thickness = 0.5.dp)
                    }
                }
            }

            // Show linear indicator at top if loading and list is already populated
            if (isLoading && proxies.isNotEmpty()) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth().align(Alignment.TopCenter))
            }
        }
    }
}

@Composable
fun ProxyRow(proxy: ProxyItem) {
    val displayType = remember(proxy.type, proxy.https) {
        val types = mutableListOf<String>()
        if (proxy.https?.lowercase() == "true") {
            types.add("SSL")
        }
        proxy.type?.split("-")?.forEach {
            if (it.isNotBlank()) {
                types.add(it.uppercase())
            }
        }
        types.joinToString(" ")
    }

    val locationText = remember(proxy.country, proxy.city) {
        if (!proxy.country.isNullOrBlank()) {
            "${proxy.country}${if (!proxy.city.isNullOrBlank()) ", ${proxy.city}" else ""}"
        } else {
            ""
        }
    }

    val timeAgo = remember(proxy.lastCheck) {
        formatTimeAgo(proxy.lastCheck)
    }

    Column(modifier = Modifier.fillMaxWidth().padding(12.dp)) {
        // First Row: Proxy Address, Time Ago, and Status
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text(
                text = proxy.proxy,
                fontWeight = FontWeight.Bold,
                fontSize = 16.sp,
                modifier = Modifier.weight(1f)
            )
            if (timeAgo.isNotEmpty()) {
                Text(
                    text = timeAgo,
                    fontSize = 11.sp,
                    color = Color.Gray,
                    modifier = Modifier.padding(end = 8.dp)
                )
            }
            Text(
                text = proxy.status ?: "unknown",
                color = if (proxy.status == "active") Color(0xFF4CAF50) else Color.Gray,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold
            )
        }

        // Second Row: Display Type and Latency
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth().padding(top = 2.dp)
        ) {
            if (displayType.isNotEmpty()) {
                Text(
                    text = displayType,
                    color = MaterialTheme.colorScheme.primary,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f)
                )
            } else {
                Spacer(modifier = Modifier.weight(1f))
            }
            Text(
                text = "${proxy.latency ?: "0"} ms",
                fontSize = 13.sp,
                color = Color.Gray,
                fontWeight = FontWeight.Medium
            )
        }

        // Third Row: Location (Only if not empty)
        if (locationText.isNotEmpty()) {
            Text(
                text = locationText,
                fontSize = 13.sp,
                color = Color.Gray,
                modifier = Modifier.padding(top = 2.dp)
            )
        }
    }
}

fun formatTimeAgo(dateString: String?): String {
    if (dateString.isNullOrBlank()) return ""
    val formats = arrayOf(
        "yyyy-MM-dd'T'HH:mm:ssXXX",
        "yyyy-MM-dd'T'HH:mm:ss",
        "yyyy-MM-dd HH:mm:ss"
    )

    var date: Date? = null
    for (format in formats) {
        try {
            val sdf = SimpleDateFormat(format, Locale.US)
            date = sdf.parse(dateString)
            if (date != null) break
        } catch (e: Exception) {
            // Try next format
        }
    }

    if (date == null) return ""

    val diff = Date().time - date.time
    if (diff < 0) return "just now" // Handle future dates gracefully

    val seconds = diff / 1000
    val minutes = seconds / 60
    val hours = minutes / 60
    val days = hours / 24

    return when {
        seconds < 60 -> "just now"
        minutes < 60 -> "${minutes}m ago"
        hours < 24 -> "${hours}h ago"
        days < 30 -> "${days}d ago"
        else -> SimpleDateFormat("MMM dd", Locale.US).format(date)
    }
}
