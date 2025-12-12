import time
from concurrent.futures import ThreadPoolExecutor, as_completed
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


def scrape(method, output, verbose, max_workers=10):
    now = time.time()
    methods = [method]
    if method == "socks":
        methods += ["socks4", "socks5"]

    proxy_scrapers = [s for s in scrapers if s.method in methods]
    if not proxy_scrapers:
        raise ValueError("Method not supported")

    verbose_print(verbose, "Scraping proxies...")
    proxies = []

    client = httpx.Client(follow_redirects=True)

    def scrape_scraper(_scraper):
        try:
            verbose_print(verbose, f"Looking {_scraper.get_url()}...")
            return _scraper.scrape(client)
        except Exception:
            return []

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [executor.submit(scrape_scraper, s) for s in proxy_scrapers]
        for fut in as_completed(futures):
            try:
                result = fut.result()
                proxies.extend(result)
            except Exception:
                pass

    client.close()

    proxies_set = set(proxies)
    verbose_print(verbose, f"Writing {len(proxies_set)} proxies to file...")
    write_file(output, "\n".join(proxies_set))
    verbose_print(verbose, "Done!")
    verbose_print(verbose, f"Took {time.time() - now} seconds")
