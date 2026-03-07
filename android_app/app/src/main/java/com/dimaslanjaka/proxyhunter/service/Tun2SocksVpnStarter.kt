package com.dimaslanjaka.proxyhunter.service

import android.content.Intent
import android.net.VpnService
import androidx.activity.ComponentActivity
import androidx.activity.result.ActivityResultLauncher
import androidx.activity.result.contract.ActivityResultContracts
import com.dimaslanjaka.prefs.LocalSharedPrefs
import timber.log.Timber

/**
 * Helper class to handle VPN permission requests and start the [Tun2SocksVpnService].
 * Must be initialized in [ComponentActivity.onCreate] or as a property initializer.
 */
class Tun2SocksVpnStarter(private val activity: ComponentActivity) {

    private val pref = LocalSharedPrefs.initialize(activity, "proxy")

    private val vpnPermissionLauncher: ActivityResultLauncher<Intent> =
        activity.registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (result.resultCode == ComponentActivity.RESULT_OK) {
                Timber.d("VPN permission granted")
                launchService()
            } else {
                Timber.w("VPN permission denied by user")
            }
        }

    /**
     * Starts the VPN service. Requests permission from the user if necessary.
     * @param ipHost The SOCKS5 proxy host/ip and port (e.g., "1.2.3.4:8080")
     */
    fun startVpn(ipHost: String) {
        val proxyUrl = if (ipHost.startsWith("socks5://")) ipHost else "socks5://$ipHost"
        Timber.i("Preparing to start VPN with proxy: %s", proxyUrl)

        pref.put("socks", proxyUrl)

        val intent = VpnService.prepare(activity)
        if (intent != null) {
            Timber.d("Requesting VPN permission")
            vpnPermissionLauncher.launch(intent)
        } else {
            Timber.d("VPN permission already granted, starting service directly")
            launchService()
        }
    }

    /**
     * Stops the VPN service.
     */
    fun stopVpn() {
        Timber.i("Stopping VPN service")
        val intent = Intent(activity, Tun2SocksVpnService::class.java)
        activity.stopService(intent)
    }

    private fun launchService() {
        try {
            val intent = Intent(activity, Tun2SocksVpnService::class.java)
            activity.startService(intent)
        } catch (e: Exception) {
            Timber.e(e, "Failed to start Tun2SocksVpnService")
        }
    }
}
