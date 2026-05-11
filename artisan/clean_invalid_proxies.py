import os
import random
import re
import signal
import sys
from pathlib import Path
from typing import List, Tuple

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from proxy_hunter import extract_proxies, is_valid_proxy

from src.func import get_relative_path
from src.func_console import green, red
from src.ProxyDB import ProxyDB
from src.shared import init_db
from src.utils.file.FileLockHelper import FileLockHelper
from src.utils.file.remove_string_from_file import remove_string_from_file


def normalize_proxy(proxy: str) -> str:
    """
    Normalize proxy IPs by removing leading zeros from IPv4 octets.

    Example:
        93.90.42.09:3065 -> 93.90.42.9:3065
    """

    pattern = r"^(\d{1,3}(?:\.\d{1,3}){3}):(\d+)$"
    proxy = proxy.strip()
    match = re.match(pattern, proxy)

    if not match:
        return proxy

    ip, port = match.groups()

    # Remove leading zeros from each octet
    octets = [str(int(octet)) for octet in ip.split(".")]

    # Keep normalization best-effort; invalid octets should be handled by is_valid_proxy.
    for octet in octets:
        if not 0 <= int(octet) <= 255:
            return proxy

    ip_port = f"{'.'.join(octets)}:{port}"
    extract = extract_proxies(ip_port)
    if extract:
        return extract[0].proxy
    return proxy


if __name__ == "__main__":
    db = init_db()
    page = 1
    per_page = 1000

    while True:
        proxies = db.get_all_proxies(page=page, per_page=per_page)
        if not proxies:
            break

        for data in proxies:
            proxy = str(data.get("proxy", ""))
            normalized_proxy = normalize_proxy(proxy)
            if not is_valid_proxy(normalized_proxy) and not is_valid_proxy(proxy):
                extract = extract_proxies(proxy)
                if extract:
                    print(
                        f"Extracted valid proxy from invalid format: {red(proxy)} -> {green(extract[0].proxy)}"
                    )
                    new_data = data.copy()
                    new_data["proxy"] = extract[0].proxy
                    try:
                        db.update_data(extract[0].proxy, new_data)
                    except Exception as e:
                        print(f"Failed to update proxy in database: {e}")
                    db.remove(proxy)
                    continue
                print(
                    f"Invalid proxy: {red(proxy)} -> Normalized: {red(normalized_proxy)}"
                )
                db.remove(proxy)
                continue
            if proxy != normalized_proxy:
                print(
                    f"Invalid proxy format: {red(proxy)} -> Normalized: {green(normalized_proxy)}"
                )
                db.remove(proxy)
                # Update the database with the normalized proxy, clone the data except for the proxy field
                new_data = data.copy()
                new_data["proxy"] = normalized_proxy
                try:
                    db.update_data(normalized_proxy, new_data)
                except Exception as e:
                    print(f"Failed to update proxy in database: {e}")
                continue

        page += 1

    try:
        db.close()
    except Exception:
        pass
