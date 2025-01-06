import os
import sys
import unittest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.ProxyDB import ProxyDB


null = None


class TestStringMethods(unittest.TestCase):

    def test_vacuum(self):
        self.db = ProxyDB()
        self.db.db.execute_query("PRAGMA journal_mode = WAL")
        self.db.db.execute_query("PRAGMA wal_autocheckpoint = 100")
        self.db.db.execute_query("PRAGMA auto_vacuum = FULL")
        self.db.db.execute_query("VACUUM")
        # https://stackoverflow.com/a/37865221/6404439
        self.db.db.execute_query("PRAGMA wal_checkpoint(SQLITE_CHECKPOINT_TRUNCATE);")

    def test_get_all(self):
        self.db = ProxyDB()
        all_p = self.db.get_all_proxies(True)[:10]
        # print(all_p)
        self.assertTrue(len(all_p) == 10)

    def test_update(self):
        self.db = ProxyDB()
        proxy = "13.208.56.180:80"
        proxy_type = "http"
        items = self.db.select(proxy)
        if items:
            item = items[0]
        else:
            item = self.db.add(proxy)[0]
        if not item.get("type"):
            # fix missing proxy type
            item["type"] = proxy_type
        item = self.db.fix_empty_single_data(item)
        print("before", item)
        # city should not be None
        self.assertTrue(item.get("latitude", None) is not None)
        self.db.update_data(proxy, {"latitude": None})
        reselect = self.db.select(proxy)[0]
        print("after", reselect)
        # city should None
        self.assertTrue(reselect.get("latitude", None) is None)


if __name__ == "__main__":
    unittest.main()
