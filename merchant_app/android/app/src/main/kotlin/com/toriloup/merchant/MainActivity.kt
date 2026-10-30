package com.toriloup.merchant

import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        super.onCreate(savedInstanceState)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                "toriloup_merchant_alerts",
                "Merchant alerts",
                NotificationManager.IMPORTANCE_DEFAULT
            ).apply {
                description = "Order, payment, return, and review alerts for your store."
            }
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }
}
