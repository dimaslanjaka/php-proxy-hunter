import re


class Scraper:
    def __init__(self, method, _url):
        self.method = method
        self._url = _url

    def get_url(self, **kwargs):
        return self._url.format(**kwargs, method=self.method)

    def get_response(self, client):
        return client.get(self.get_url())

    def handle(self, response):
        return response.text

    def scrape(self, client):
        response = self.get_response(client)
        proxies = self.handle(response)
        pattern = re.compile(r"\d{1,3}(?:\.\d{1,3}){3}(?::\d{1,5})?")
        return re.findall(pattern, proxies)
