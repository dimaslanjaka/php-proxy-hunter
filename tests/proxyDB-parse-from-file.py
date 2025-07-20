import atexit
import os
import sys
from typing import Optional

from proxy_hunter import Proxy, file_remove_empty_lines

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


from src.func import get_relative_path, remove_string_and_move_to_file
from src.func_console import green, red
from proxy_hunter import check_proxy
from src.ProxyDB import ProxyDB

db = ProxyDB()
proxy_file = get_relative_path("proxies.txt")
dead_file = get_relative_path("dead.txt")


def clean_files():
    file_remove_empty_lines(proxy_file)
    file_remove_empty_lines(dead_file)


atexit.register(clean_files)


def process_proxy(item: Optional[Proxy]):
    check_http = check_proxy(item.proxy, "http", "http://httpbin.org/ip")
    check_s5 = check_proxy(item.proxy, "socks5", "http://httpbin.org/ip")
    check_s4 = check_proxy(item.proxy, "socks4", "http://httpbin.org/ip")
    working = False
    if check_http.result:
        print(item.proxy, "working", green("HTTP"))
        working = True
    if check_s5.result:
        print(item.proxy, "working", green("SOCKS5"))
        working = True
    if check_s4.result:
        print(item.proxy, "working", green("SOCKS4"))
        working = True
    if not working:
        print(item.proxy, red("dead"))
    else:
        db.update_status(item.proxy, "active")
    remove_string_and_move_to_file(proxy_file, dead_file, item.proxy)


db.from_file(proxy_file, process_proxy)
