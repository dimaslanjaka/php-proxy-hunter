import atexit
import concurrent.futures
import logging
import os
import random
import re
import threading
from typing import Callable, Dict, List, Optional, Tuple, Union

from proxy_hunter.cidr2ips import list_ips_from_cidr
from proxy_hunter.curl.prox_check import is_prox
from proxy_hunter.curl.proxy_utils import is_port_open
from proxy_hunter.extractor import extract_ips
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

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s[%(threadName)s] %(message)s",
)


def gen_ports(proxy: str, force: bool = False):
    """
    Generate ports from IP and save to tmp/ips-ports/IP.txt.
    Refer to cidr-information/genPorts.php
    """
    ips = extract_ips(proxy)
    for ip in ips:
        ip_port_pairs = generate_ip_port_pairs(ip, 80)
        ip_ports = [":".join(map(str, item)) for item in ip_port_pairs]
        file = f"tmp/ip-ports/{ip}.txt"
        if not os.path.exists(file) or force:
            write_file(file, "\n".join(ip_ports))
            logging.info(f"Generated {len(ip_ports)} proxies on {file}")


at_exit_data: Dict[str, List[str]] = {}


def process_iterated_proxy(
    proxy: str, ip: str, callback: Optional[Callable[[str, bool, bool], None]] = None
):
    file = f"tmp/ip-ports/{ip}.txt"
    is_open = is_port_open(proxy)
    is_proxy = False
    logging.info(f"{proxy} - {'port open' if is_open else 'port closed'}")
    if is_open:
        is_proxy = is_prox(proxy)
        logging.info(f"{proxy} - {'is proxy' if is_proxy else 'not proxy'}")
    if callable(callback):
        callback(proxy, is_open, is_proxy)
    at_exit_data[ip].append(proxy)
    remove_string_from_file(file, at_exit_data[ip], True)


def iterate_gen_ports(
    proxy: str, callback: Optional[Callable[[str, bool, bool], None]] = None
):
    global at_exit_data
    ips = extract_ips(proxy)
    for ip in ips:
        if ip not in at_exit_data:
            at_exit_data[ip] = []
        file = f"tmp/ip-ports/{ip}.txt"
        if not os.path.exists(file):
            logging.warning(f"{file} not found")
            return
        text: str = read_file(file)
        lines: List[str] = re.split(r"\r?\n", text)
        pattern = re.compile(r"^\d{1,3}(\.\d{1,3}){3}:\d+$")
        filtered_lines = [line for line in lines if pattern.match(line)]
        random.shuffle(filtered_lines)
        logging.info(f"Got {len(filtered_lines)} proxies extracted from {file}")

        with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
            futures = []
            for line_proxy in filtered_lines:
                futures.append(
                    executor.submit(process_iterated_proxy, line_proxy, ip, callback)
                )

            # Optional: Wait for all futures to complete
            concurrent.futures.wait(futures)


def register_exit():
    global at_exit_data
    for ip, data in at_exit_data.items():
        file = f"tmp/ip-ports/{ip}.txt"
        remove_string_from_file(file, data, True)


atexit.register(register_exit)

if __name__ == "__main__":
    proxy = "156.34.105.58:5678"
    gen_ports(proxy)
    iterate_gen_ports(proxy)
