from bs4 import BeautifulSoup
from proxy_hunter.scrappers.scrapper_base import Scraper


# For websites using div in html
class GeneralDivScraper(Scraper):
    def handle(self, response):
        soup = BeautifulSoup(response.text, "html.parser")
        proxies = set()
        table = soup.find("div", attrs={"class": "list"})
        if not table:
            return ""
        for row in table.find_all("div"):
            count = 0
            proxy = ""
            for cell in row.find_all("div", attrs={"class": "td"}):
                if count == 2:
                    break
                proxy += cell.text + ":"
                count += 1
            proxy = proxy.rstrip(":")
            proxies.add(proxy)
        return "\n".join(proxies)


# For websites using table in html
class GeneralTableScraper(Scraper):
    def handle(self, response):
        soup = BeautifulSoup(response.text, "html.parser")
        proxies = set()
        table = soup.find(
            "table", attrs={"class": "table table-striped table-bordered"}
        )
        if not table:
            return ""
        for row in table.find_all("tr"):
            count = 0
            proxy = ""
            for cell in row.find_all("td"):
                if count == 1:
                    proxy += ":" + cell.text.replace("&nbsp;", "")
                    proxies.add(proxy)
                    break
                proxy += cell.text.replace("&nbsp;", "")
                count += 1
        return "\n".join(proxies)
