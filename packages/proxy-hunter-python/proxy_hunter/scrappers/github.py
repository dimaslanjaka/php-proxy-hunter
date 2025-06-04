from proxy_hunter.scrappers.scrapper_base import Scraper


# For scraping live proxylist from GitHub
class GitHubScraper(Scraper):

    async def handle(self, response):
        tempproxies = response.text.split("\n")
        proxies = set()
        for prxy in tempproxies:
            if self.method in prxy:
                proxies.add(prxy.split("//")[-1])

        return "\n".join(proxies)
