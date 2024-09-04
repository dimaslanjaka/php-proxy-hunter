import unittest
import certifi
from requests import Response

from proxy_hunter.curl.request_helper import build_request


class TestMyClass(unittest.TestCase):

    def test_without_proxy(self):
        response = self.do_request()
        self.assertEqual(response.status_code, 200)

    def test_with_proxy(self):
        response = None
        proxy = "117.40.32.135:8080"
        try:
            response = self.do_request(
                proxy=proxy, proxy_type="http", verify=certifi.where()
            )
        except Exception:
            pass
        if not response:
            try:
                response = self.do_request(
                    proxy=proxy, proxy_type="socks4", verify=certifi.where()
                )
            except Exception:
                pass
        if not response:
            try:
                response = self.do_request(
                    proxy=proxy, proxy_type="socks5", verify=certifi.where()
                )
            except Exception:
                pass
        self.assertTrue(isinstance(response, Response))
        if response:
            self.assertEqual(response.status_code, 200)
            self.assertTrue("<title>Example Domain</title>" in response.text)

    def do_request(self, **kwargs):
        try:
            return build_request(endpoint="https://www.example.com", **kwargs)
        except Exception:
            return None


if __name__ == "__main__":
    unittest.main()
