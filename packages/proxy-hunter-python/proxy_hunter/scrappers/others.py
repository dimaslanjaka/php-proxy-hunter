from proxy_hunter.scrappers.scrapper_base import Scraper


# From proxy-list.download
class ProxyListDownloadScraper(Scraper):

    def __init__(self, method, anon):
        self.anon = anon
        super().__init__(
            method,
            "https://www.proxy-list.download/api/v1/get?type={method}&anon={anon}",
        )

    def get_url(self, **kwargs):
        return super().get_url(anon=self.anon, **kwargs)


# From geonode.com - A little dirty, grab http(s) and socks but use just for socks
class GeoNodeScraper(Scraper):

    def __init__(
        self, method, limit="500", page="1", sort_by="lastChecked", sort_type="desc"
    ):
        self.limit = limit
        self.page = page
        self.sort_by = sort_by
        self.sort_type = sort_type
        super().__init__(
            method,
            "https://proxylist.geonode.com/api/proxy-list?"
            "&limit={limit}"
            "&page={page}"
            "&sort_by={sort_by}"
            "&sort_type={sort_type}",
        )

    def get_url(self, **kwargs):
        return super().get_url(
            limit=self.limit,
            page=self.page,
            sort_by=self.sort_by,
            sort_type=self.sort_type,
            **kwargs
        )


# From proxyscrape.com
class ProxyScrapeScraper(Scraper):

    def __init__(self, method, timeout=1000, country="All"):
        self.timout = timeout
        self.country = country
        super().__init__(
            method,
            "https://api.proxyscrape.com/?request=getproxies"
            "&proxytype={method}"
            "&timeout={timout}"
            "&country={country}",
        )

    def get_url(self, **kwargs):
        return super().get_url(timout=self.timout, country=self.country, **kwargs)
