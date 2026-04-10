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
 */
class Tun2SocksVpnStarter(private val activity: ComponentActivity) {

    private val pref = LocalSharedPrefs.initialize(activity, "proxy")

    private val vpnPermissionLauncher: ActivityResultLauncher<Intent> =
        activity.registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
            if (result.resultCode == ComponentActivity.RESULT_OK) {
                Timber.d("VPN permission granted")
                launchService(onStarted)
            } else {
                Timber.w("VPN permission denied by user")
            }
        }

    private var onStarted: (() -> Unit)? = null

    fun startVpn(ipHost: String, onStarted: (() -> Unit)? = null) {
        val proxyUrl = if (ipHost.startsWith("socks5://")) ipHost else "socks5://$ipHost"
        Timber.i("Starting VPN with proxy: %s", proxyUrl)
        this.onStarted = onStarted

        pref.put("socks", proxyUrl)

        val intent = VpnService.prepare(activity)
        if (intent != null) {
            vpnPermissionLauncher.launch(intent)
        } else {
            launchService(this.onStarted)
        }
    }

    fun stopVpn() {
        Timber.i("Stopping VPN service action")
        pref.remove("socks")
        val intent = Intent(activity, Tun2SocksVpnService::class.java).apply {
            action = Tun2SocksVpnService.ACTION_STOP
        }
        activity.startService(intent)
    }

    private fun launchService(onStarted: (() -> Unit)?) {
        try {
            val intent = Intent(activity, Tun2SocksVpnService::class.java)
            activity.startService(intent)
            onStarted?.invoke()
            this.onStarted = null
        } catch (e: Exception) {
            Timber.e(e, "Failed to start Tun2SocksVpnServiceOfficial")
            this.onStarted = null
        }
    }
}
