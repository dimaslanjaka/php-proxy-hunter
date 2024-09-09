import atexit
import concurrent.futures
import os
import random
import re
from typing import Callable, Dict, List, Tuple
import threading
from proxy_hunter.cidr2ips import list_ips_from_cidr
from proxy_hunter.curl.prox_check import is_prox
from proxy_hunter.curl.proxy_utils import is_port_open
from proxy_hunter.ip2cidr import calculate_cidr
from proxy_hunter.ip2proxy_list import generate_ip_port_pairs
from proxy_hunter.ip2subnet import get_default_subnet_mask
from proxy_hunter.utils.file import (
    delete_path,
    load_tuple_from_file,
    read_file,
    remove_string_from_file,
    save_tuple_to_file,
    write_file,
)


def gen_ports(proxy: str, force: bool = False):
    """
    generate ports from IP and save to tmp/ips-ports/IP.txt.
    refer to cidr-information/genPorts.php
    """
    ip, port = proxy.split(":")
    ip_port_pairs = generate_ip_port_pairs(ip, 80)
    ip_ports = [":".join(map(str, item)) for item in ip_port_pairs]
    file = f"tmp/ip-ports/{ip}.txt"
    if not os.path.exists(file) or force:
        write_file(file, "\n".join(ip_ports))
        print(f"generated {len(ip_ports)} proxies on {file}")


at_exit_data: Dict[str, List[str]] = {}


def iterate_gen_ports(proxy: str):
    global at_exit_data
    ip, port = proxy.split(":")
    if ip not in at_exit_data:
        at_exit_data[ip] = []
    file = f"tmp/ip-ports/{ip}.txt"
    if not os.path.exists(file):
        print(file, "not found")
        return
    text: str = read_file(file)
    lines: List[str] = re.split(r"\r?\n", text)
    random.shuffle(lines)
    print("got", len(lines), "proxies extracted from", file)
    for proxy in lines:
        is_open = is_port_open(proxy)
        print(proxy, "port open" if is_open else "port closed")
        if is_open:
            is_proxy = is_prox(proxy)
            print(proxy, "is proxy" if is_proxy else "not proxy")
        at_exit_data[ip].append(proxy)
        threading.Thread(target=remove_string_from_file, args=(file, proxy)).start()


def register_exit():
    global at_exit_data
    for ip, data in at_exit_data.items():
        file = f"tmp/ip-ports/{ip}.txt"
        remove_string_from_file(file, data)


atexit.register(register_exit)

if __name__ == "__main__":
    proxy = "156.34.105.58:5678"
    gen_ports(proxy)
    iterate_gen_ports(proxy)
