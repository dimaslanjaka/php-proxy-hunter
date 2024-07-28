import unittest, os, sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_proxy import build_request

null = None


class TestStringMethods(unittest.TestCase):

    def test_curl(self):
        self.assertEqual("foo".upper(), "FOO")
        response = build_request(null, null, "GET", null, "https://yahoo.com")
        self.assertTrue(response.status_code == 200)


if __name__ == "__main__":
    unittest.main()
