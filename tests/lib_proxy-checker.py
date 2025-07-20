import os
import sys

from proxy_hunter import remove_string_from_file

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import *
from src.func_proxy import *
from src.ProxyDB import Proxy, ProxyDB

db = ProxyDB()
checker = ProxyChecker()
proxy_file = get_relative_path("proxies.txt")


def process_proxy(item: Proxy):
    result = checker.check_proxy(item.proxy)
    if not result:
        db.update_status(item.proxy, "dead")
        remove_string_from_file(proxy_file, item.proxy)
        print(item.proxy, "dead")
    else:
        db.update_status(item.proxy, "active")
        print(result)


db.from_file(proxy_file, process_proxy)
