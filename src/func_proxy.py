import http.client as http_client
import logging
import os
import random
import sqlite3
import ssl
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any, Dict, List, Optional, Union

import requests
import urllib3
from proxy_hunter import (
    check_proxy,
    extract_ips,
    file_append_str,
    file_remove_empty_lines,
    get_pc_useragent,
    get_unique_dicts_by_key_in_list,
    is_port_open,
    move_string_between,
    read_all_text_files,
    read_file,
)

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path
from src.func_certificate import output_pem
from src.func_console import debug_log, get_caller_info, green, log_proxy, red
from src.func_date import is_date_rfc3339_hour_more_than
from src.func_platform import is_debug, is_django_environment
from src.ProxyDB import ProxyDB

# Set the certificate file in environment variables
os.environ["REQUESTS_CA_BUNDLE"] = output_pem
os.environ["SSL_CERT_FILE"] = output_pem

# Replace create default https context method
ssl._create_default_https_context = lambda: ssl.create_default_context(
    cafile=output_pem
)

# Suppress InsecureRequestWarning
requests.packages.urllib3.disable_warnings()  # type: ignore
urllib3.disable_warnings()


def requests_enable_verbose():
    """
    Enable verbose logging for debugging HTTP requests.
    """
    http_client.HTTPConnection.debuglevel = 1
    logging.basicConfig()
    logging.getLogger().setLevel(logging.DEBUG)
    requests_log = logging.getLogger("requests.packages.urllib3")
    requests_log.setLevel(logging.DEBUG)
    requests_log.propagate = True


def upload_proxy(proxy: Any) -> None:
    """
    Uploads a proxy to a specific URL.

    Args:
        proxy (str): The proxy to upload.

    Returns:
        None: No return value.
    """
    if not is_debug():
        # production mode
        if is_django_environment():
            # skip called by django
            return
    if not isinstance(proxy, str):
        proxy = str(proxy)
    if len(proxy.strip()) > 10:
        cookies = {
            "__ga": "GA1.2.1234567890.1234567890",
            "_ga": "GA1.3.9876543210.9876543210",
        }
        response = send_post(
            url="https://sh.webmanajemen.com/proxyAdd.php",
            data={"proxies": proxy},
            cookies=cookies,
        )
        debug_log(f"{proxy} uploaded -> {response}".strip())
        time.sleep(1)


def check_proxy_new(proxy: str):
    db = ProxyDB()
    logfile = get_relative_path("proxyChecker.txt")
    status = None
    working = False
    protocols = []
    print(f"check_proxy_new -> {proxy}")
    if not is_port_open(proxy):
        log_proxy(f"{proxy} {red('port closed')}")
        status = "port-closed"
    else:
        # Define a function to handle check_proxy with the correct arguments
        def handle_check(proxy, protocol, url):
            return check_proxy(proxy, protocol, url)

        # Create a ThreadPoolExecutor
        with ThreadPoolExecutor(max_workers=3) as executor:
            # Submit the tasks
            checks = [
                executor.submit(handle_check, proxy, "http", "http://httpbin.org/ip"),
                executor.submit(handle_check, proxy, "socks4", "http://httpbin.org/ip"),
                executor.submit(handle_check, proxy, "socks5", "http://httpbin.org/ip"),
            ]

            # Iterate through the completed tasks
            for i, future in enumerate(as_completed(checks)):
                protocol = ["HTTP", "SOCKS4", "SOCKS5"][i]
                check = future.result()

                if check.result:
                    log = f"> {proxy} ✓ {protocol}"
                    protocols.append(protocol.lower())
                    file_append_str(logfile, log)
                    print(green(log))
                    working = True
                else:
                    log = f"> {proxy} 🗙 {protocol}"
                    file_append_str(logfile, f"{log} -> {check.error}")
                    print(f"{red(log)} -> {check.error}")
                    working = False

            if not working:
                status = "dead"
            else:
                status = "active"
                upload_proxy(proxy)

    if db is not None and status is not None:
        data = {"status": status}
        if len(protocols) > 0:
            data["type"] = "-".join(protocols).upper()
        db.update_data(proxy, data)

    move_string_between(
        get_relative_path("proxies.txt"), get_relative_path("dead.txt"), proxy
    )
    file_remove_empty_lines(logfile)


def get_proxies(
    working_only: bool = False,
    untested_only: bool = False,
    skip_last_check_filter: bool = False,
) -> List[Dict[str, str]]:
    """
    Get proxies based on status while excluding dead/private proxies.

    Args:
        working_only:
            Only return active proxies.

        untested_only:
            Only return untested proxies.

        skip_last_check_filter:
            Skip stale last_check validation when fetching
            random fallback proxies.
    """
    db = ProxyDB(get_relative_path("src/database.sqlite"), True)

    try:
        database = db.get_db()

        proxies: list[dict[str, str]] = []

        append = proxies.extend

        select = database.select

        # =====================================================
        # Database selection
        # =====================================================

        if untested_only or not working_only:
            append(select("proxies", "*", "status = ?", ["untested"]))

        if working_only or not untested_only:
            append(select("proxies", "*", "status = ?", ["active"]))

        # =====================================================
        # File extraction
        # =====================================================

        if not working_only and not untested_only:
            files_content = read_all_text_files(get_relative_path("assets/proxies"))

            proxy_file_path = get_relative_path("proxies.txt")

            if os.path.exists(proxy_file_path):
                files_content[proxy_file_path] = str(read_file(proxy_file_path))

            for file_path, content in files_content.items():
                extracted = db.extract_proxies(content, True)

                total = len(extracted)

                print(f"Total proxies extracted from {file_path} is {total}")

                if total:
                    append(item.to_dict() for item in extracted)

            # =================================================
            # Ensure minimum proxies
            # =================================================

            if len(proxies) < 100:
                append(select("proxies", "*", "status = ?", ["active"], True))

            if len(proxies) < 100:
                append(select("proxies", "*", "status = ?", ["untested"], True))

            # =================================================
            # Re-check stale proxies
            # =================================================

            if len(proxies) < 100:
                append(
                    item
                    for item in select("proxies", "*", rand=True)
                    if item.get("proxy")
                    and (
                        skip_last_check_filter
                        or is_date_rfc3339_hour_more_than(
                            item.get("last_check"),
                            1,
                        )
                    )
                )

        # =====================================================
        # Deduplicate + filtering
        # =====================================================

        seen: set[str] = set()

        filtered: list[dict[str, str]] = []

        for proxy in proxies:
            proxy_value = proxy.get("proxy")

            if not proxy_value or proxy_value in seen or is_private_or_dead(proxy):
                continue

            seen.add(proxy_value)

            filtered.append(proxy)

        if not filtered:
            log_proxy("proxies empty")

            file, line = get_caller_info()

            debug_log(f"Called from file '{file}', line {line}")

            return []

        random.shuffle(filtered)

        return filtered

    finally:
        db.close()


def is_proxy_recently_checked(proxy: Dict[str, Union[None, str]]):
    if proxy["last_check"] is None:
        return True
    return is_date_rfc3339_hour_more_than(proxy["last_check"], 24)


def is_private_or_dead(proxy: Dict[str, str]) -> bool:
    return proxy.get("private") == "true" or proxy.get("status") in (
        "port-closed",
        "dead",
    )


def check_all_proxies(count: int = 10):
    proxies = get_proxies()
    # log_proxy(f"Total untested proxies ({len(proxies)})")

    # Filter out invalid proxies
    valid_proxies = [
        item["proxy"]
        for item in proxies[:count]
        if item and item["proxy"].strip() and len(item["proxy"].strip()) >= 10
    ]

    with ThreadPoolExecutor() as executor:
        # Map each proxy to check_proxy_new function
        executor.map(check_proxy_new, valid_proxies)


def is_post_length_within_limit(data_string: str, limit_mb: float = 8.0) -> bool:
    """
    Check if the length of the given string when encoded in UTF-8 is within the specified limit in megabytes.

    Args:
        data_string (str): The string whose length needs to be checked.
        limit_mb (float, optional): The maximum allowed size in megabytes. Defaults to 8.0.

    Returns:
        bool: True if the length is within the limit, False otherwise.
    """
    # Convert string to bytes
    data_bytes = data_string.encode("utf-8")

    # Calculate the size in MB
    size_mb = len(data_bytes) / (1024 * 1024)  # 1 MB = 1024 * 1024 bytes

    # Check if the size is within the limit
    return size_mb <= limit_mb


def truncate_string_size(data_string: str, max_size_mb: float = 2.0) -> str:
    """
    Truncate the given string to a maximum size in megabytes if its size exceeds the specified limit.

    Args:
        data_string (str): The string to be truncated.
        max_size_mb (float, optional): The maximum allowed size in megabytes. Defaults to 2.0.

    Returns:
        str: The truncated string.
    """
    # Convert string to bytes
    data_bytes = data_string.encode("utf-8")

    # Calculate the maximum number of bytes allowed for the specified size in MB
    max_bytes = int(max_size_mb * 1024 * 1024)

    # Check if size exceeds the specified limit
    if len(data_bytes) > max_bytes:
        # Truncate the string
        truncated_bytes = data_bytes[:max_bytes]

        # Decode the truncated bytes back to string
        truncated_string = truncated_bytes.decode("utf-8")

        return truncated_string

    return data_string


def send_post(
    url: str,
    data: Dict[str, Union[str, int]],
    cookies: Optional[Dict[str, str]] = None,
    headers: Optional[Dict[str, str]] = None,
) -> Union[str, None]:
    """
    Make a POST request with SSL verification.

    Args:
        url (str): The URL to which the POST request will be sent.
        data (Dict[str, Union[str, int]]): The data to be sent with the POST request.
        cookies (Dict[str, str], optional): Dictionary of cookies to attach to the request. Default is None.
        headers (Dict[str, str], optional): Dictionary of HTTP headers to attach to the request. Default is None.

    Returns:
        Union[str, None]: The response text if the request is successful, otherwise None.
    """
    default_headers = {"User-Agent": get_pc_useragent()}
    if headers is not None:
        default_headers.update(headers)
    try:
        session = requests.Session()
        response = session.post(
            url=url, data=data, cookies=cookies, headers=default_headers
        )
        if response.status_code == 200:
            return response.text
        else:
            return f"Error: {response.status_code} - {response.text}"
    except Exception as e:
        return f"Error: {str(e)}"


def blacklist_remover(
    db_path: str = "src/database.sqlite", blacklist_file: str = "data/blacklist.conf"
):
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()

    r_blacklist = read_file(blacklist_file)
    if r_blacklist:
        blacklist = extract_ips(r_blacklist)
        for ip in blacklist:
            query = 'DELETE FROM "proxies" WHERE "proxy" LIKE ? AND status != "active"'
            proxy = f"%{ip}%"
            cursor.execute(query, (proxy,))
            affected_rows = cursor.rowcount
            print(f"[BLACKLIST] {ip} deleted {affected_rows} row(s).")

    conn.commit()
    conn.close()


def get_blacklist(blacklist_file: str = "data/blacklist.conf"):
    """Get blacklisted IPs"""
    r_blacklist = read_file(blacklist_file)
    if r_blacklist:
        blacklist = extract_ips(r_blacklist)
        return blacklist
