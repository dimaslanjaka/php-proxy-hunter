import concurrent.futures
import os
from typing import Tuple, List

from proxy_hunter.cidr2ips import list_ips_from_cidr
from proxy_hunter.curl.prox_check import is_prox
from proxy_hunter.ip2cidr import calculate_cidr
from proxy_hunter.ip2proxy_list import generate_ip_port_pairs
from proxy_hunter.ip2subnet import get_default_subnet_mask
from proxy_hunter.utils.file import (
    delete_path,
    load_tuple_from_file,
    save_tuple_to_file,
)


def process_proxies_chunk(proxies_chunk: List[Tuple[str, int]], cache_file: str):
    def callback(proxy: Tuple[str, int]):
        ip, port = proxy
        proxy_str = f"{ip}:{port}"
        check = is_prox(proxy_str)
        print(f"{proxy_str} {'is proxy' if check is not None else 'is not proxy'}\t")
        return proxy if check else None

    with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
        results = list(executor.map(callback, proxies_chunk))

    # Filter out the None results and update the cache file
    proxies_chunk = [result for result in results if result is not None]
    return proxies_chunk


def chunks_generator(proxy: str):
    ip, port = proxy.split(":")
    cache_file = f"tmp/data/cache-{ip}.tuple"
    iter_data = f"tmp/data/{ip}.txt"
    if os.path.exists(cache_file):
        try:
            proxies = load_tuple_from_file(cache_file)
            print("cached generated ip port pairs loaded")
        except Exception as e:
            print(f"fail load cached generated ip port pairs: {e}")
            delete_path(cache_file)
            delete_path(iter_data)
            return chunks_generator(proxy)
    else:
        print("regenerating ip port pairs")
        subnet_mask = get_default_subnet_mask(ip)
        cidr = calculate_cidr(ip, subnet_mask)
        ips = list_ips_from_cidr(cidr)
        proxies = [pair for ip in ips for pair in generate_ip_port_pairs(ip)]
        save_tuple_to_file(cache_file, proxies)

    chunk_size = 1000  # Process in chunks of [n] proxies
    # all_filtered_proxies: List[Tuple[str, int]] = []

    for i in range(0, len(proxies), chunk_size):
        proxies_chunk = proxies[i : i + chunk_size]
        chunk_cache_file = f"tmp/data/cache-{ip}-chunk-{i}.tuple"
        if not os.path.exists(chunk_cache_file):
            print(f"save {len(proxies_chunk)} chunk items to {chunk_cache_file}")
            save_tuple_to_file(chunk_cache_file, proxies_chunk)
    #     filtered_proxies = process_proxies_chunk(proxies_chunk, cache_file)
    #     all_filtered_proxies.extend(filtered_proxies)
    #     # Optionally, save progress to file here if desired

    # save_tuple_to_file(cache_file, all_filtered_proxies)


if __name__ == "__main__":
    chunks_generator("156.34.105.58:5678")
