import os
import sys
import unittest
import requests

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import build_request


class TestStringMethods(unittest.TestCase):
    def test_curl(self):
        response = build_request(
            None, None, "GET", None, endpoint="https://yahoo.com", verify=True
        )
        self.assertTrue(response.status_code == 200)

    def test_with_proxy_auth(self):
        proxy = "http://qtculbqe:iazrxzml7g27@31.59.20.176:6754"
        response = build_request(
            proxy, None, "GET", None, endpoint="https://yahoo.com", verify=True
        )
        self.assertIsNotNone(response)
        self.assertIsInstance(response, requests.Response)
        self.assertGreater(len(response.content), 0)


if __name__ == "__main__":
    unittest.main()
