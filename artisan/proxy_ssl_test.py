import argparse
import asyncio
import os
import re
import socket
import ssl
import sys
from typing import Any, Dict, List, Literal, Sequence

import httpx
import requests
from bs4 import BeautifulSoup
from dotenv import find_dotenv, load_dotenv
from proxy_hunter import extract_proxies, build_request

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from artisan.proxy_checker_httpx import test_proxy
from src.ASNLookup import ASNLookup
from src.func import get_relative_path
from src.func_console import ConsoleColor, green, magenta, red, yellow
from src.func_date import get_current_rfc3339_time, is_date_rfc3339_hour_more_than
from src.func_platform import is_debug
from src.ProxyDB import ProxyDB
from src.shared import init_db, init_readonly_db
from src.utils.file.FileLockHelper import FileLockHelper

env_file = find_dotenv(filename=".env", usecwd=True)
load_dotenv(env_file)

current_filename = os.path.basename(__file__)
locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
if not locker.lock():
    print(red("Another instance is running. Exiting."))
    sys.exit(0)

ProxyType = Literal["http", "socks4", "socks5"]


def test_mozilla(proxy: str, proxy_type: ProxyType, timeout: int = 10) -> bool:
    """
    Test whether a proxy can access https://www.mozilla.org/en-US/
    and verify the page title starts with "Mozilla".

    :param proxy: Proxy in format "host:port" or "user:pass@host:port"
    :param proxy_type: "http", "socks4", or "socks5"
    :param timeout: Request timeout in seconds
    :return: True if working and title starts with "Mozilla", else False
    """
    url = "https://www.mozilla.org/en-US/"

    try:
        response = build_request(
            proxy=proxy,
            proxy_type=proxy_type,
            endpoint=url,
            method="GET",
            headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"},
            timeout=timeout,
            keep_headers=True,
        )

        response.raise_for_status()

        match = re.search(
            r"<title>(.*?)</title>", response.text, re.IGNORECASE | re.DOTALL
        )
        if not match:
            return False

        title = match.group(1).strip()

        return title.startswith("Mozilla")

    except Exception as e:
        print(red(f"[ERROR] Exception during Mozilla test for proxy {proxy}: {e}"))
        return False


def test_httpforever(proxy: str, proxy_type: ProxyType, timeout: int = 10) -> bool:
    """
    Test whether a proxy can access http://httpforever.com/
    and verify the page title starts with "http".

    :param proxy: Proxy in format "host:port" or "user:pass@host:port"
    :param proxy_type: "http", "socks4", or "socks5"
    :param timeout: Request timeout in seconds
    :return: True if working and title starts with "http", else False
    """
    url = "http://httpforever.com/"

    try:
        response = build_request(
            proxy=proxy,
            proxy_type=proxy_type,
            endpoint=url,
            method="GET",
            headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"},
            timeout=timeout,
            keep_headers=True,
        )

        response.raise_for_status()

        match = re.search(
            r"<title>(.*?)</title>", response.text, re.IGNORECASE | re.DOTALL
        )
        if not match:
            return False

        title = match.group(1).strip()

        return title.lower().startswith("http")

    except Exception as e:
        print(red(f"[ERROR] Exception during httpforever test for proxy {proxy}: {e}"))
        return False


def check_http_ssl(host, port, timeout=5):
    try:
        s = socket.create_connection((host, port), timeout=timeout)
        req = (
            "CONNECT www.google.com:443 HTTP/1.1\r\n"
            "Host: www.google.com:443\r\n"
            "User-Agent: ProxyHunter\r\n"
            "\r\n"
        )
        s.sendall(req.encode())
        s.settimeout(timeout)
        resp = s.recv(4096)
        s.close()
        return b"200" in resp
    except:
        return False


def check_socks5(host, port, timeout=5):
    try:
        s = socket.create_connection((host, port), timeout=timeout)
        s.sendall(b"\x05\x01\x00")  # greeting: no auth
        if s.recv(2) != b"\x05\x00":
            return False

        req = (
            b"\x05\x01\x00\x01" + socket.inet_aton("1.1.1.1") + (443).to_bytes(2, "big")
        )
        s.sendall(req)
        resp = s.recv(10)
        s.close()
        return resp[1] == 0x00
    except:
        return False


def check_socks4(host, port, timeout=5):
    try:
        s = socket.create_connection((host, port), timeout=timeout)
        req = (
            b"\x04\x01"
            + (443).to_bytes(2, "big")
            + socket.inet_aton("1.1.1.1")
            + b"\x00"
        )
        s.sendall(req)
        resp = s.recv(8)
        s.close()
        return resp[1] == 0x5A
    except:
        return False


def detect_proxy_type(proxy_str: str, timeout=5):
    proxies = extract_proxies(proxy_str)
    if not proxies:
        return None

    host, port = proxies[0].proxy.split(":")
    port = int(port)

    if check_http_ssl(host, port, timeout):
        return "http+ssl"
    if check_socks5(host, port, timeout):
        return "socks5"
    if check_socks4(host, port, timeout):
        return "socks4"

    return None


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Proxy SSL test tool.")
    parser.add_argument(
        "--readonly", action="store_true", help="Use readonly DB connection"
    )
    args = parser.parse_args()

    if args.readonly:
        db = init_readonly_db()
    else:
        db_host = os.getenv("MYSQL_HOST", "localhost")
        db_user = os.getenv("MYSQL_USER", "root")
        db_pass = os.getenv("MYSQL_PASS", "")
        db = ProxyDB(
            db_type="mysql",
            start=True,
            db_location="tmp/database.sqlite",
            mysql_dbname="php_proxy_hunter_test",
            mysql_host=db_host,
            mysql_user=db_user,
            mysql_password=db_pass,
        )
    proxies = db.get_working_proxies(randomize=True)

    for data in proxies:
        proxy = data["proxy"]
        if is_date_rfc3339_hour_more_than(data.get("last_check"), 24) is False:
            print(
                yellow(f"[SKIP] Proxy checked within last 24 hours, skipping: {proxy}")
            )
            continue
        if not proxy:
            continue

        ptype = detect_proxy_type(proxy)

        if ptype == "http+ssl":
            if test_mozilla(proxy, "http"):
                print(green(f"[OK] HTTP SSL (Mozilla) {proxy}"))
                db.update_data(
                    proxy,
                    {
                        "https": "true",
                        "type": "http",
                        "last_check": get_current_rfc3339_time(),
                    },
                )
            else:
                print(red(f"[FAIL] Mozilla test failed: {proxy}"))
        elif ptype == "socks5":
            if test_mozilla(proxy, "socks5"):
                print(magenta(f"[OK] SOCKS5 (Mozilla) {proxy}"))
                db.update_data(
                    proxy,
                    {
                        "https": "true",
                        "type": "socks5",
                        "last_check": get_current_rfc3339_time(),
                    },
                )
            else:
                print(red(f"[FAIL] Mozilla test failed: {proxy}"))
        elif ptype == "socks4":
            if test_mozilla(proxy, "socks4"):
                print(yellow(f"[OK] SOCKS4 (Mozilla) {proxy}"))
                db.update_data(
                    proxy,
                    {
                        "https": "true",
                        "type": "socks4",
                        "last_check": get_current_rfc3339_time(),
                    },
                )
            else:
                print(red(f"[FAIL] Mozilla test failed: {proxy}"))
        else:
            if (
                not test_mozilla(proxy, "http")
                and not test_mozilla(proxy, "socks5")
                and not test_mozilla(proxy, "socks4")
            ):
                if (
                    not test_httpforever(proxy, "http")
                    and not test_httpforever(proxy, "socks5")
                    and not test_httpforever(proxy, "socks4")
                ):
                    print(red(f"[DEAD] Non-SSL/Dead proxy: {proxy}"))
                    db.update_data(
                        proxy,
                        {
                            "https": "false",
                            "last_check": get_current_rfc3339_time(),
                            "status": "dead",
                        },
                    )
                else:
                    print(
                        red(
                            f"[FAIL] Could not determine type and proxy failed Mozilla test but works on httpforever: {proxy}"
                        )
                    )
                    db.update_data(
                        proxy,
                        {"https": "false", "last_check": get_current_rfc3339_time()},
                    )
            else:
                print(
                    yellow(
                        f"[UNKNOWN] Could not determine type but proxy works: {proxy}"
                    )
                )
