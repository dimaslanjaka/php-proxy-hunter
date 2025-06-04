from proxy_hunter.scrappers.scrapper_base import Scraper


class SpysMeScraper(Scraper):
    def __init__(self, method):
        super().__init__(method, "https://spys.me/{mode}.txt")

    def get_url(self, **kwargs):
        mode = (
            "proxy"
            if self.method == "http"
            else "socks" if self.method == "socks" else "unknown"
        )
        if mode == "unknown":
            raise NotImplementedError
        return super().get_url(mode=mode, **kwargs)
