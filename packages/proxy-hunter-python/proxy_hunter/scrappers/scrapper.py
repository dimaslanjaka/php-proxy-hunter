import time
import asyncio
import httpx
from proxy_hunter.scrappers.github import GitHubScraper
from proxy_hunter.scrappers.html_parser import GeneralTableScraper, GeneralDivScraper
from proxy_hunter.scrappers.others import (
    ProxyListDownloadScraper,
    GeoNodeScraper,
    ProxyScrapeScraper,
)
from proxy_hunter.scrappers.spysme import SpysMeScraper
from proxy_hunter.utils.file import write_file
from proxy_hunter.utils.index_utils import verbose_print

scrapers = [
    SpysMeScraper("http"),
    SpysMeScraper("socks"),
    ProxyScrapeScraper("http"),
    ProxyScrapeScraper("socks4"),
    ProxyScrapeScraper("socks5"),
    GeoNodeScraper("socks"),
    ProxyListDownloadScraper("https", "elite"),
    ProxyListDownloadScraper("http", "elite"),
    ProxyListDownloadScraper("http", "transparent"),
    ProxyListDownloadScraper("http", "anonymous"),
    GeneralTableScraper("https", "http://sslproxies.org"),
    GeneralTableScraper("http", "http://free-proxy-list.net"),
    GeneralTableScraper("http", "http://us-proxy.org"),
    GeneralTableScraper("socks", "http://socks-proxy.net"),
    GeneralDivScraper("http", "https://freeproxy.lunaproxy.com/"),
    GitHubScraper(
        "http",
        "https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/all/data.txt",
    ),
    GitHubScraper(
        "socks4",
        "https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/all/data.txt",
    ),
    GitHubScraper(
        "socks5",
        "https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/all/data.txt",
    ),
    GitHubScraper(
        "http",
        "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/all.txt",
    ),
    GitHubScraper(
        "socks",
        "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/all.txt",
    ),
    GitHubScraper(
        "https", "https://raw.githubusercontent.com/zloi-user/hideip.me/main/https.txt"
    ),
    GitHubScraper(
        "http", "https://raw.githubusercontent.com/zloi-user/hideip.me/main/http.txt"
    ),
    GitHubScraper(
        "socks4",
        "https://raw.githubusercontent.com/zloi-user/hideip.me/main/socks4.txt",
    ),
    GitHubScraper(
        "socks5",
        "https://raw.githubusercontent.com/zloi-user/hideip.me/main/socks5.txt",
    ),
]


async def scrape(method, output, verbose):
    now = time.time()
    methods = [method]
    if method == "socks":
        methods += ["socks4", "socks5"]

    proxy_scrapers = [s for s in scrapers if s.method in methods]
    if not proxy_scrapers:
        raise ValueError("Method not supported")

    verbose_print(verbose, "Scraping proxies...")
    proxies = []
    tasks = []

    client = httpx.AsyncClient(follow_redirects=True)

    async def scrape_scraper(_scraper):
        try:
            verbose_print(verbose, f"Looking {_scraper.get_url()}...")
            proxies.extend(await _scraper.scrape(client))
        except Exception:
            pass

    for scraper in proxy_scrapers:
        tasks.append(asyncio.ensure_future(scrape_scraper(scraper)))

    await asyncio.gather(*tasks)
    await client.aclose()

    proxies = set(proxies)
    verbose_print(verbose, f"Writing {len(proxies)} proxies to file...")
    write_file(output, "\n".join(proxies))
    verbose_print(verbose, "Done!")
    verbose_print(verbose, f"Took {time.time() - now} seconds")
