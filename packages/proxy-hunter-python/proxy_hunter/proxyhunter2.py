import os
from proxy_hunter.cidr2ips import list_ips_from_cidr
from proxy_hunter.ip2cidr import calculate_cidr
from proxy_hunter.ip2proxy_list import generate_ip_port_pairs
from proxy_hunter.ip2subnet import get_default_subnet_mask
from proxy_hunter.prox_check import is_prox
from proxy_hunter.utils import IterationHelper
import json


if __name__ == "__main__":
    ip, port = "156.34.105.58:5678".split(":")
    cache_file = "tmp/data/cache-{}.json".format(ip)
    if os.path.exists(cache_file):
        pass

    subnet_mask = get_default_subnet_mask(ip)
    cidr = calculate_cidr(ip, subnet_mask)
    ips = list_ips_from_cidr(cidr)
    proxies = [generate_ip_port_pairs(ip) for ip in ips]

    def callback(proxy: str):
        # check = is_prox(proxy) is not None
        # print("{} is proxy {}".format(proxy, str(check)))
        print(proxy)

    iterator = IterationHelper(
        proxies,
        callback,
        f"tmp/data/{ip}.txt",
    )
    iterator.run()