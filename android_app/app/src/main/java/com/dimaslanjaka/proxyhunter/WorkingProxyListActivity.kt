package com.dimaslanjaka.proxyhunter

import android.content.res.Configuration
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.IntrinsicSize
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material.icons.filled.Power
import androidx.compose.material.icons.filled.PowerOff
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.dimaslanjaka.proxyhunter.data.ProxyDB
import com.dimaslanjaka.proxyhunter.data.ProxyItem
import com.dimaslanjaka.prefs.LocalSharedPrefs
import com.dimaslanjaka.proxyhunter.service.Tun2SocksVpnStarter
import com.dimaslanjaka.proxyhunter.ui.theme.ProxyHunterTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import timber.log.Timber
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class WorkingProxyList : ComponentActivity() {
    private lateinit var vpnStarter: Tun2SocksVpnStarter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        vpnStarter = Tun2SocksVpnStarter(this)

        enableEdgeToEdge()
        setContent {
            ProxyHunterTheme {
                ProxyListScreen(
                    onConnect = { proxy ->
                        vpnStarter.startVpn(proxy.proxy)
                    },
                    onDisconnect = {
                        vpnStarter.stopVpn()
                    },
                    onBack = {
                        finish()
                    }
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProxyListScreen(onConnect: (ProxyItem) -> Unit, onDisconnect: () -> Unit, onBack: () -> Unit) {
    val scope = rememberCoroutineScope()
    var proxies by remember { mutableStateOf(listOf<ProxyItem>()) }
    var isLoading by remember { mutableStateOf(false) }
    var connectedProxyUrl by remember { mutableStateOf<String?>(null) }
    val context = LocalContext.current

    var countries by remember { mutableStateOf(listOf<String>()) }
    var cities by remember { mutableStateOf(listOf<String>()) }
    var classifications by remember { mutableStateOf(listOf<String>()) }
    var selectedCountry by remember { mutableStateOf<String?>(null) }
    var selectedCity by remember { mutableStateOf<String?>(null) }
    var selectedClassification by remember { mutableStateOf<String?>(null) }

    val pageSize = 20
    var offset by remember { mutableIntStateOf(0) }
    var canLoadMore by remember { mutableStateOf(true) }

    val listState = rememberLazyListState()

    val loadProxies = { isNextPage: Boolean ->
        if (!isLoading && (isNextPage && canLoadMore || !isNextPage)) {
            scope.launch {
                isLoading = true
                if (!isNextPage) {
                    offset = 0
                    canLoadMore = true
                }

                val db = ProxyDB()
                try {
                    val currentOffset = if (isNextPage) offset else 0
                    val result = withContext(Dispatchers.IO) {
                        db.getWorkingProxies(
                            limit = pageSize,
                            offset = currentOffset,
                            country = selectedCountry,
                            city = selectedCity,
                            classification = selectedClassification
                        ).get()
                    }

                    if (!isNextPage) {
                        proxies = result
                    } else {
                        // Prevent "Duplicate Key" crash by filtering out items with already loaded IDs
                        val combined = (proxies + result).distinctBy { it.id }
                        proxies = combined
                    }

                    if (result.size < pageSize) {
                        canLoadMore = false
                    }
                    offset = proxies.size
                } catch (e: Exception) {
                    Timber.tag("ProxyHunter").e(e, "UI Load failed")
                } finally {
                    db.close()
                    isLoading = false
                }
            }
        }
    }

    // Infinite scroll detection using state
    val shouldLoadMore = remember {
        derivedStateOf {
            val lastVisibleItem = listState.layoutInfo.visibleItemsInfo.lastOrNull()
            lastVisibleItem != null && lastVisibleItem.index >= proxies.size - 5
        }
    }

    LaunchedEffect(shouldLoadMore.value) {
        if (shouldLoadMore.value && canLoadMore && !isLoading && proxies.isNotEmpty()) {
            loadProxies(true)
        }
    }

    // Load initial filters on startup and check VPN status
    LaunchedEffect(Unit) {
        val pref = try { LocalSharedPrefs.initialize(context, "proxy") } catch (_: Exception) { null }
        connectedProxyUrl = pref?.getString("socks", null)

        val db = ProxyDB()
        try {
            countries = withContext(Dispatchers.IO) { db.getUniqueCountries().get() }
            classifications = withContext(Dispatchers.IO) { db.getUniqueClassifications().get() }
        } catch (e: Exception) {
            Timber.tag("ProxyHunter").e(e, "Failed to fetch filters")
        } finally {
            db.close()
        }
        loadProxies(false)
    }

    // Load cities when country changes
    LaunchedEffect(selectedCountry) {
        selectedCity = null
        val db = ProxyDB()
        try {
            cities = withContext(Dispatchers.IO) { db.getUniqueCities(selectedCountry).get() }
        } catch (e: Exception) {
            Timber.tag("ProxyHunter").e(e, "Failed to fetch cities")
        } finally {
            db.close()
        }
        loadProxies(false)
    }

    // Refresh when city or classification changes
    LaunchedEffect(selectedCity, selectedClassification) {
        loadProxies(false)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Working Proxies") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    IconButton(onClick = { loadProxies(false) }, enabled = !isLoading) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                }
            )
        }
    ) { innerPadding ->
        Box(modifier = Modifier.fillMaxSize().padding(innerPadding)) {
            Column(modifier = Modifier.fillMaxSize()) {
                // Filters Row
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(IntrinsicSize.Min)
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    FilterDropdown(
                        label = "Country",
                        options = countries,
                        selectedOption = selectedCountry,
                        onOptionSelected = { selectedCountry = it },
                        modifier = Modifier.width(150.dp).fillMaxHeight()
                    )
                    Spacer(modifier = Modifier.size(8.dp))
                    FilterDropdown(
                        label = "City",
                        options = cities,
                        selectedOption = selectedCity,
                        onOptionSelected = { selectedCity = it },
                        modifier = Modifier.width(150.dp).fillMaxHeight()
                    )
                    Spacer(modifier = Modifier.size(8.dp))
                    FilterDropdown(
                        label = "Classification",
                        options = classifications,
                        selectedOption = selectedClassification,
                        onOptionSelected = { selectedClassification = it },
                        modifier = Modifier.width(150.dp).fillMaxHeight()
                    )
                }

                Box(modifier = Modifier.weight(1f)) {
                    if (proxies.isEmpty() && !isLoading) {
                        Text("No active proxies found.", modifier = Modifier.align(Alignment.Center))
                    } else {
                        LazyColumn(
                            state = listState,
                            modifier = Modifier.fillMaxSize()
                        ) {
                            itemsIndexed(
                                items = proxies,
                                key = { index, proxy -> if (proxy.id > 0) proxy.id else "item-$index" } // Performance: unique keys
                            ) { _, proxy ->
                                ProxyRow(
                                    proxy = proxy,
                                    connectedProxyUrl = connectedProxyUrl,
                                    onConnect = {
                                        onConnect(proxy)
                                        connectedProxyUrl = if (proxy.proxy.startsWith("socks5://")) proxy.proxy else "socks5://${proxy.proxy}"
                                    },
                                    onDisconnect = {
                                        onDisconnect()
                                        connectedProxyUrl = null
                                    }
                                )
                                HorizontalDivider(color = Color.LightGray, thickness = 0.5.dp)
                            }

                            if (isLoading && proxies.isNotEmpty()) {
                                item {
                                    Box(
                                        modifier = Modifier.fillMaxWidth().padding(16.dp),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        CircularProgressIndicator(modifier = Modifier.size(24.dp))
                                    }
                                }
                            }
                        }
                    }

                    if (isLoading && proxies.isNotEmpty() && offset == 0) {
                        LinearProgressIndicator(modifier = Modifier.fillMaxWidth().align(Alignment.TopCenter))
                    }
                }
            }

            if (isLoading && proxies.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.Center))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun FilterDropdown(
    label: String,
    options: List<String>,
    selectedOption: String?,
    onOptionSelected: (String?) -> Unit,
    modifier: Modifier = Modifier
) {
    var expanded by remember { mutableStateOf(false) }

    Box(modifier = modifier) {
        OutlinedTextField(
            value = selectedOption ?: "All $label",
            onValueChange = {},
            readOnly = true,
            label = { Text(label, maxLines = 1) },
            singleLine = true,
            modifier = Modifier.fillMaxWidth().fillMaxHeight(),
            trailingIcon = {
                Icon(
                    Icons.Default.ArrowDropDown,
                    contentDescription = null,
                    modifier = Modifier.clickable { expanded = !expanded }
                )
            },
            textStyle = MaterialTheme.typography.bodyMedium.copy(fontSize = 11.sp)
        )
        Box(
            modifier = Modifier.matchParentSize().clickable { expanded = !expanded }
        )
        DropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false },
            modifier = Modifier.width(150.dp)
        ) {
            DropdownMenuItem(
                text = { Text("All $label", fontSize = 12.sp) },
                onClick = { onOptionSelected(null); expanded = false }
            )
            options.forEach { option ->
                DropdownMenuItem(
                    text = { Text(option, fontSize = 12.sp) },
                    onClick = { onOptionSelected(option); expanded = false }
                )
            }
        }
    }
}

@Composable
fun ProxyRow(
    proxy: ProxyItem,
    connectedProxyUrl: String?,
    onConnect: () -> Unit,
    onDisconnect: () -> Unit
) {
    val fullProxyUrl = remember(proxy.proxy) {
        if (proxy.proxy.startsWith("socks5://")) proxy.proxy else "socks5://${proxy.proxy}"
    }
    val isConnected = connectedProxyUrl == fullProxyUrl

    val displayType = remember(proxy.type, proxy.https) {
        val types = mutableSetOf<String>()
        if (proxy.https?.lowercase() == "true") types.add("SSL")

        proxy.type?.split("-", ",")
            ?.map { it.trim().uppercase() }
            ?.filter { it.isNotBlank() }
            ?.forEach { protocol ->
                types.add(protocol)
            }

        // Merge SOCKS4/A and SOCKS5/H to SOCKS4A/5H or SOCKS4/5 to simplify
        val hasSocks5 = types.any { it.startsWith("SOCKS5") }
        val hasSocks4 = types.any { it.startsWith("SOCKS4") }

        if (hasSocks4 && hasSocks5) {
            val hasSocks5h = types.contains("SOCKS5H")
            val hasSocks4a = types.contains("SOCKS4A")

            // Clear existing socks types
            types.removeIf { it.startsWith("SOCKS") }

            if (hasSocks4a && hasSocks5h) {
                types.add("SOCKS4A/5H")
            } else {
                types.add("SOCKS4/5")
            }
        } else {
            // Still simplify individual variants if only one family is present
            if (types.contains("SOCKS5H")) {
                types.remove("SOCKS5")
            }
            if (types.contains("SOCKS4A")) {
                types.remove("SOCKS4")
            }
        }

        val list = types.toList()
        if (list.size > 2) {
            "${list.take(2).joinToString(" ")} +${list.size - 2}"
        } else {
            list.joinToString(" ")
        }
    }

    val locationText = remember(proxy.country, proxy.city) {
        if (!proxy.country.isNullOrBlank()) {
            "${proxy.country}${if (!proxy.city.isNullOrBlank()) ", ${proxy.city}" else ""}"
        } else ""
    }

    val timeAgo = remember(proxy.lastCheck) { formatTimeAgo(proxy.lastCheck) }

    val configuration = LocalConfiguration.current
    val isSmallDevice = configuration.screenWidthDp <= 400
    val isLandscape = configuration.orientation == Configuration.ORIENTATION_LANDSCAPE
    val showButtonText = !isSmallDevice || isLandscape

    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp, horizontal = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                Text(
                    text = proxy.proxy,
                    fontWeight = FontWeight.Bold,
                    fontSize = 15.sp,
                    modifier = Modifier.weight(1f),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                if (timeAgo.isNotEmpty()) {
                    Text(text = timeAgo, fontSize = 11.sp, color = Color.Gray, modifier = Modifier.padding(horizontal = 4.dp), maxLines = 1)
                }
                Text(
                    text = proxy.status ?: "unknown",
                    color = if (proxy.status == "active") Color(0xFF4CAF50) else Color.Gray,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1
                )
            }

            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().padding(top = 2.dp)) {
                if (displayType.isNotEmpty()) {
                    Text(text = displayType, color = MaterialTheme.colorScheme.primary, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(end = 6.dp), maxLines = 1)
                }
                if (!proxy.classification.isNullOrBlank()) {
                   Box(modifier = Modifier.background(color = MaterialTheme.colorScheme.secondaryContainer, shape = RoundedCornerShape(4.dp)).padding(horizontal = 4.dp, vertical = 1.dp)) {
                       Text(text = proxy.classification.uppercase(), color = MaterialTheme.colorScheme.onSecondaryContainer, fontSize = 9.sp, fontWeight = FontWeight.Bold, maxLines = 1)
                   }
                }
                Spacer(modifier = Modifier.weight(1f))
                Text(text = "${proxy.latency ?: "0"} ms", fontSize = 12.sp, color = Color.Gray, fontWeight = FontWeight.Medium, maxLines = 1)
            }

            if (locationText.isNotEmpty()) {
                Text(text = locationText, fontSize = 12.sp, color = Color.Gray, modifier = Modifier.padding(top = 2.dp), maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }

        Spacer(modifier = Modifier.size(8.dp))

        Button(
            onClick = if (isConnected) onDisconnect else onConnect,
            modifier = if (showButtonText) Modifier.height(32.dp) else Modifier.size(36.dp),
            contentPadding = if (showButtonText) PaddingValues(horizontal = 8.dp, vertical = 0.dp) else PaddingValues(0.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = if (isConnected) MaterialTheme.colorScheme.errorContainer else MaterialTheme.colorScheme.primaryContainer,
                contentColor = if (isConnected) MaterialTheme.colorScheme.onErrorContainer else MaterialTheme.colorScheme.onPrimaryContainer
            ),
            shape = RoundedCornerShape(8.dp)
        ) {
            Icon(
                if (isConnected) Icons.Default.PowerOff else Icons.Default.Power,
                contentDescription = if (isConnected) "Disconnect" else "Connect",
                modifier = Modifier.size(18.dp)
            )
            if (showButtonText) {
                Spacer(modifier = Modifier.size(4.dp))
                Text(if (isConnected) "Stop" else "Connect", fontSize = 11.sp)
            }
        }
    }
}

private val timeFormats = listOf(
    SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", Locale.US),
    SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US),
    SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
)

fun formatTimeAgo(dateString: String?): String {
    if (dateString.isNullOrBlank()) return ""
    var date: Date? = null
    for (sdf in timeFormats) {
        try {
            date = sdf.parse(dateString)
            if (date != null) break
        } catch (_: Exception) { }
    }
    if (date == null) return ""

    val diff = Date().time - date.time
    if (diff < 0) return "just now"
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

@Preview(showBackground = true)
@Composable
fun ProxyRowPreview() {
    ProxyHunterTheme {
        ProxyRow(
            proxy = ProxyItem(proxy = "192.168.1.1:8080", status = "active", latency = "150"),
            connectedProxyUrl = null,
            onConnect = {},
            onDisconnect = {}
        )
    }
}

@Preview(showBackground = true)
@Composable
fun ProxyListScreenPreview() {
    ProxyHunterTheme {
        ProxyListScreen(onConnect = {}, onDisconnect = {}, onBack = {})
    }
}
