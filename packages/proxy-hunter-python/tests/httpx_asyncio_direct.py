import asyncio
from typing import Optional

from bs4 import BeautifulSoup
from proxy_hunter.curl.httpx_asyncio import *

# require: pip install httpx[socks] beautifulsoup4


async def print_proxy_result(result: ProxyCheckHTTPXResult, protocol: str) -> None:
    """Print proxy check result with page title if available."""
    print(f"{protocol}: status_code={result.status_code}, error={result.error}")

    if result.response is not None and "text/html" in result.response.headers.get(
        "content-type", ""
    ):
        # Parse HTML to get title
        soup = BeautifulSoup(result.response.text, "html.parser")
        title: Optional[str] = (
            soup.title.string.strip() if soup.title and soup.title.string else None
        )
        print(f"{protocol}: page title={title}")


async def main():
    proxy = "87.255.28.101:1080"
    endpoint = "https://opencode.ai"
    print(f"Checking proxy {proxy} -> {endpoint}...\n")

    # Check HTTP proxy
    http_result = await check_http_proxy(proxy, endpoint)
    await print_proxy_result(http_result, "HTTP")

    # Check SOCKS5 proxy
    socks5_result = await check_socks5_proxy(proxy, endpoint)
    await print_proxy_result(socks5_result, "SOCKS5")


asyncio.run(main())
