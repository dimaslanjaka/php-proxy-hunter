import traceback
import argparse
from time import sleep
from typing import Optional
from src.func import get_relative_path
from proxy_hunter.utils.file import truncate_file_content
from src.func_proxy import check_all_proxies


def run_proxy_checker(max_proxies: Optional[int] = None):
    if not max_proxies:
        max_proxies = 100
    truncate_file_content(get_relative_path("proxyChecker.txt"))
    try:
        check_all_proxies(max_proxies)
    except Exception as e:
        print("fail checking all proxies", e)
        traceback.print_exc()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Proxy Checker")
    parser.add_argument("--max", type=int, help="Maximum number of proxies to check")
    args = parser.parse_args()
    if args.max:
        max_proxies = args.max
    else:
        max_proxies = 100
    run_proxy_checker(max_proxies)
    sleep(3)  # Wait 3 seconds before exit
