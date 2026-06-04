import asyncio

from proxy_hunter.curl.httpx_asyncio import *

# require: pip install httpx[socks]


async def main():
    proxy = "87.255.28.101:1080"
    endpoint = "https://api.ipify.org?format=json"
    print(f"Checking proxy {proxy} -> {endpoint}...\n")

    result = await check_http_proxy(proxy, endpoint)
    print(f"HTTP: status_code={result.status_code}, error={result.error}")

    result = await check_socks5_proxy(proxy, endpoint)
    print(f"SOCKS5: status_code={result.status_code}, error={result.error}")


asyncio.run(main())
