package com.dimaslanjaka.proxyhunter.data

data class ProxyItem(
    val id: Int = 0,
    val proxy: String,
    val latency: String? = null,
    val lastCheck: String? = null,
    val type: String? = null,
    val region: String? = null,
    val city: String? = null,
    val country: String? = null,
    val timezone: String? = null,
    val latitude: String? = null,
    val longitude: String? = null,
    val anonymity: String? = null,
    val https: String? = null,
    val status: String? = null,
    val private: String? = null,
    val lang: String? = null,
    val useragent: String? = null,
    val webglVendor: String? = null,
    val webglRenderer: String? = null,
    val browserVendor: String? = null,
    val username: String? = null,
    val password: String? = null
) {
    val host: String
        get() = proxy.split(":")[0]

    val port: Int
        get() = proxy.split(":").getOrNull(1)?.toIntOrNull() ?: 0

    companion object {
        fun fromHostPort(host: String, port: Int): ProxyItem {
            return ProxyItem(proxy = "$host:$port")
        }
    }
}
