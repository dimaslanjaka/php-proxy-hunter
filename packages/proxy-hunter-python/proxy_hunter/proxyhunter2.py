import os
from typing import Tuple

from proxy_hunter.cidr2ips import list_ips_from_cidr
from proxy_hunter.curl.prox_check import is_prox
from proxy_hunter.ip2cidr import calculate_cidr
from proxy_hunter.ip2proxy_list import generate_ip_port_pairs
from proxy_hunter.ip2subnet import get_default_subnet_mask
from proxy_hunter.utils import IterationHelper
from proxy_hunter.utils.file import (
    delete_path,
    write_json,
    load_tuple_from_file,
    save_tuple_to_file,
)


def proxy_hunter2(proxy: str):
    ip, port = proxy.split(":")
    cache_file = "tmp/data/cache-{}.json".format(ip)
    iter_data = f"tmp/data/{ip}.txt"
    if os.path.exists(cache_file):
        try:
            proxies = load_tuple_from_file(cache_file)
            print("cached generated ip port pairs loaded")
        except Exception as e:
            print(f"fail load cached generated ip port pairs: {e}")
            delete_path(cache_file)
            delete_path(iter_data)
            return proxy_hunter2(proxy)
    else:
        print("regenerating ip port pairs")
        subnet_mask = get_default_subnet_mask(ip)
        cidr = calculate_cidr(ip, subnet_mask)
        ips = list_ips_from_cidr(cidr)
        proxies = [pair for ip in ips for pair in generate_ip_port_pairs(ip)]
        save_tuple_to_file(cache_file, proxies)

    def callback(proxy: Tuple[str, int]):
        nonlocal proxies
        ip, port = proxy
        proxy_str = f"{ip}:{port}"
        check = is_prox(proxy_str)
        print("{} is proxy {}".format(proxy_str, str(check)))
        proxies = [item for item in proxies if item != proxy]
        save_tuple_to_file(cache_file, proxies)

    iterator = IterationHelper(proxies, callback, iter_data)
    iterator.run()


if __name__ == "__main__":
    proxy_hunter2("156.34.105.58:5678")
