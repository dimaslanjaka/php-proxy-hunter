import os
import sys
import unittest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.ProxyDB import ProxyDB


null = None


class TestStringMethods(unittest.TestCase):

    def test_curl(self):
        self.assertEqual("foo".upper(), "FOO")
        self.db = ProxyDB()
        all_p = self.db.get_all_proxies(True)[:10]
        print(all_p)
        self.assertTrue(len(all_p) == 10)


if __name__ == "__main__":
    unittest.main()
