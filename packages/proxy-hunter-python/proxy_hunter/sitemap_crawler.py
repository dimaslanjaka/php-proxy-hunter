from typing import List
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
import time


def get_internal_links(url: str, domain: str) -> set:
    """Get internal links from a given URL."""
    internal_links = set()
    try:
        response = requests.get(url)
        if response.status_code == 200:
            soup = BeautifulSoup(response.content, "html.parser")
            for a_tag in soup.find_all("a", href=True):
                link = a_tag["href"]
                full_url = urljoin(url, link)
                parsed_url = urlparse(full_url)
                if parsed_url.netloc == domain and full_url not in internal_links:
                    internal_links.add(full_url)
    except requests.RequestException as e:
        print(f"Error fetching {url}: {e}")
    return internal_links


def crawl_sitemap(start_url: str, limit: int = None) -> List[str]:
    """Crawl and build a sitemap starting from the initial URL with an optional limit."""
    domain = urlparse(start_url).netloc
    visited = set()
    to_visit = set([start_url])
    sitemap: List[str] = []

    while to_visit and (limit is None or len(sitemap) < limit):
        url = to_visit.pop()
        if url in visited:
            continue

        visited.add(url)
        sitemap.append(url)
        internal_links = get_internal_links(url, domain)
        print(f"{url} got {len(internal_links)} internal links")
        to_visit.update(internal_links - visited)

        # Respectful crawling delay
        time.sleep(1)

    return sitemap


if __name__ == "__main__":
    initial_url = "https://sh.webmanajemen.com:8443"  # Replace with your initial URL
    limit = 500  # Set the maximum number of URLs to crawl (set to None for no limit)

    sitemap = crawl_sitemap(initial_url, limit)

    print("Sitemap:")
    for url in sitemap:
        print(url)
