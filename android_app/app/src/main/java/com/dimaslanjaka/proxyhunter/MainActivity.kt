package com.dimaslanjaka.proxyhunter

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.automirrored.filled.PlaylistAddCheck
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import com.dimaslanjaka.proxyhunter.data.ProxyDB
import com.dimaslanjaka.proxyhunter.data.ProxyManager
import com.dimaslanjaka.proxyhunter.service.ProxyCheckService
import com.dimaslanjaka.proxyhunter.ui.theme.ProxyHunterTheme
import timber.log.Timber

class MainActivity : ComponentActivity() {

    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted: Boolean ->
        if (isGranted) {
            // Permission is granted. Continue the action or workflow in your app.
            fetchUntestedAndStartService()
        } else {
            // Explain to the user that the feature is unavailable because the
            // features requires a permission that the user has denied.
            Toast.makeText(this, "Notification permission is required for background checking", Toast.LENGTH_LONG).show()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        checkNotificationPermission()

        enableEdgeToEdge()
        setContent {
            ProxyHunterTheme {
                MainMenuScreen(
                    onNavigateToWorkingProxies = {
                        startActivity(Intent(this, WorkingProxyList::class.java))
                    },
                    onNavigateToProxyChecker = {
                        startActivity(Intent(this, ProxyCheckerActivity::class.java))
                    }
                )
            }
        }
    }

    private fun checkNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            when {
                ContextCompat.checkSelfPermission(
                    this,
                    Manifest.permission.POST_NOTIFICATIONS
                ) == PackageManager.PERMISSION_GRANTED -> {
                    // You can use the API that requires the permission.
                    fetchUntestedAndStartService()
                }
                shouldShowRequestPermissionRationale(Manifest.permission.POST_NOTIFICATIONS) -> {
                    // In an educational UI, explain to the user why your app requires this
                    // permission for a specific feature to behave as expected.
                    requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
                }
                else -> {
                    // You can directly ask for the permission.
                    requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
                }
            }
        } else {
            fetchUntestedAndStartService()
        }
    }

    private fun fetchUntestedAndStartService() {
        val proxyDB = ProxyDB()
        Thread {
            try {
                Timber.tag("ProxyHunter").d("Checking for untested proxies to start service...")
                val proxies = proxyDB.getUntestedProxies(limit = 100).get()
                if (proxies.isNotEmpty()) {
                    Timber.tag("ProxyHunter").d("Found ${proxies.size} untested proxies. Starting service.")
                    ProxyManager.set(proxies)
                    val intent = Intent(this, ProxyCheckService::class.java)
                    ContextCompat.startForegroundService(this, intent)
                }
            } catch (e: Exception) {
                Timber.tag("ProxyHunter").e(e, "Service initialization failed")
            } finally {
                proxyDB.close()
            }
        }.start()
    }
}

data class MenuItem(
    val title: String,
    val description: String,
    val icon: ImageVector,
    val onClick: () -> Unit
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainMenuScreen(
    onNavigateToWorkingProxies: () -> Unit,
    onNavigateToProxyChecker: () -> Unit
) {
    val menuItems = listOf(
        MenuItem(
            title = "Working Proxies",
            description = "View and connect to available proxy servers",
            icon = Icons.AutoMirrored.Filled.List,
            onClick = onNavigateToWorkingProxies
        ),
        MenuItem(
            title = "Proxy Checker",
            description = "Manually check a list of proxies",
            icon = Icons.AutoMirrored.Filled.PlaylistAddCheck,
            onClick = onNavigateToProxyChecker
        )
    )

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Proxy Hunter") }
            )
        }
    ) { innerPadding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            items(menuItems) { item ->
                MenuListItem(item)
                HorizontalDivider(modifier = Modifier.padding(horizontal = 16.dp))
            }
        }
    }
}

@Composable
fun MenuListItem(item: MenuItem) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { item.onClick() }
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = item.icon,
            contentDescription = null,
            modifier = Modifier.size(24.dp),
            tint = MaterialTheme.colorScheme.primary
        )
        Spacer(modifier = Modifier.width(16.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = item.title,
                style = MaterialTheme.typography.titleMedium
            )
            Text(
                text = item.description,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
        Icon(
            imageVector = Icons.Default.ChevronRight,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Preview(showBackground = true)
@Composable
fun MainMenuScreenPreview() {
    ProxyHunterTheme {
        MainMenuScreen(
            onNavigateToWorkingProxies = {},
            onNavigateToProxyChecker = {}
        )
    }
}
