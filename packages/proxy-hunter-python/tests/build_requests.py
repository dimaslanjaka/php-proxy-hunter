import unittest

from proxy_hunter.curl.request_helper import build_request


class TestMyClass(unittest.TestCase):

    def test_x(self):
        response = self.do_request()
        self.assertEqual(response.status_code, 200)

    def do_request(self, proxy=None):
        try:
            return build_request(endpoint="https://www.example.com", proxy=proxy)
        except Exception:
            return None


if __name__ == "__main__":
    unittest.main()
