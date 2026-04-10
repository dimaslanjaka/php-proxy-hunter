package com.dimaslanjaka.proxyhunter.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.dimaslanjaka.proxyhunter.AutoCheckStarter

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED || intent.action == "android.intent.action.QUICKBOOT_POWERON") {
            AutoCheckStarter.maybeStart(context, "boot-completed")
        }
    }
}
