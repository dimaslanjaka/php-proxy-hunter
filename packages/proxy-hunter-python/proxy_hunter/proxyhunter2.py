import atexit
import concurrent.futures
import os
import random
import re
import signal
import sys
import threading
from typing import Callable, Dict, List, Optional

from proxy_hunter.curl.prox_check import is_prox
from proxy_hunter.curl.proxy_utils import is_port_open
from proxy_hunter.extractor import extract_ips
from proxy_hunter.ip2proxy_list import generate_ip_port_pairs
from proxy_hunter.utils.file import read_file, remove_string_from_file, write_file

# Define a global counter and lock for controlling output
print_lock = threading.Lock()
LINE_CLEAR = "\x1b[2K"


def print_status(message: str, end="\n"):
    with print_lock:
        if "port closed" in message:
            sys.stdout.write(message + end)
        else:
            sys.stdout.write("\n" + message + end)
        sys.stdout.flush()


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
            print_status(f"Generated {len(ip_ports)} proxies on {file}", end="\n")


at_exit_data: Dict[str, List[str]] = {}


def process_iterated_proxy(
    proxy: str, ip: str, callback: Optional[Callable[[str, bool, bool], None]] = None
):
    file = f"tmp/ip-ports/{ip}.txt"
    is_open = is_port_open(proxy)
    is_proxy = False
    print_status(
        f"{proxy} - {'port open' if is_open else 'port closed'}",
        end="\r" if not is_open else "\n",
    )
    if is_open:
        is_proxy = is_prox(proxy)
        print_status(
            f"{proxy} - {'is proxy' if is_proxy else 'not proxy'}",
            end="\r" if not is_proxy else "\n",
        )
    if callable(callback):
        callback(proxy, is_open, is_proxy)
    at_exit_data[ip].append(proxy)
    remove_string_from_file(file, proxy, True)


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
            print_status(f"{file} not found", end="\r")
            return
        text: str = read_file(file)
        lines: List[str] = re.split(r"\r?\n", text)
        pattern = re.compile(r"^\d{1,3}(\.\d{1,3}){3}:\d+$")
        filtered_lines = [line for line in lines if pattern.match(line)]
        random.shuffle(filtered_lines)
        print_status(
            f"Got {len(filtered_lines)} proxies extracted from {file}", end="\n"
        )

        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as executor:
            futures = []
            for line_proxy in filtered_lines:
                futures.append(
                    executor.submit(process_iterated_proxy, line_proxy, ip, callback)
                )

            # Optional: Wait for all futures to complete
            concurrent.futures.wait(futures)


def register_exit(signum=None, frame=None):
    global at_exit_data
    for ip, data in at_exit_data.items():
        file = f"tmp/ip-ports/{ip}.txt"
        remove_string_from_file(file, data, True)


atexit.register(register_exit)
signal.signal(signal.SIGTERM, register_exit)
signal.signal(signal.SIGINT, register_exit)  # To handle Ctrl+C

if __name__ == "__main__":
    proxy = "156.34.105.58:5678"
    gen_ports(proxy)
    iterate_gen_ports(proxy)
