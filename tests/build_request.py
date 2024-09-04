import os
import sys
import unittest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter.curl.build_requests import build_request

null = None


class TestStringMethods(unittest.TestCase):

    def test_curl(self):
        response = build_request(null, null, "GET", null, endpoint="https://yahoo.com")
        self.assertTrue(response.status_code == 200)


if __name__ == "__main__":
    unittest.main()
