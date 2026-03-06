package com.dimaslanjaka.proxyhunter.data

object ProxyManager {
    private var proxies: List<ProxyItem> = emptyList()

    @JvmStatic
    fun set(list: List<ProxyItem>) {
        proxies = list
    }

    @JvmStatic
    fun get(): List<ProxyItem> {
        return proxies
    }
}
