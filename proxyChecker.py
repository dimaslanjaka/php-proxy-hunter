import traceback
from time import sleep
from typing import Optional
from src.func import get_relative_path
from proxy_hunter import truncate_file_content
from src.func_proxy import check_all_proxies
from src.utils.parse_args import parse_args


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
    # Use shared parser which supports --limit and --max
    args = parse_args(default_limit=100, description="Proxy Checker")
    max_proxies = int(getattr(args, "limit", 100) or 100)
    run_proxy_checker(max_proxies)
    sleep(3)  # Wait 3 seconds before exit
