import os
import sys
import asyncio
import random
import re
from typing import Any, List, Optional, Set

from bs4 import BeautifulSoup
from proxy_hunter import build_request, get_device_ip

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src import ProxyDB
from src.func import get_relative_path
from src.func_console import cyan, red, magenta, green
from src.utils.file import remove_string_from_file
from src.utils.file.FileLockHelper import FileLockHelper
from artisan.proxy_getter import (
    normalize_proxy_value,
    retrieve_proxies,
    ProxyRetrievalResult,
)
from src.utils.parse_args import parse_args
from src.func_date import get_current_rfc3339_time, is_date_rfc3339_older_than
from src.shared import init_db, init_readonly_db

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


async def check_proxy_https(
    proxy: str,
    timeout: int = 10,
    url: str = "https://www.google.com",
    expected_title: str = "Google",
    **kwargs,
) -> bool:
    """
    Async wrapper: run blocking `build_request` in a thread and validate the page title for HTTPS proxies.

    Args:
        proxy: Proxy string to use for the request (e.g., 'https://user:pass@host:port').
        timeout: Request timeout in seconds.
        url: The HTTPS URL to check (default: 'https://www.google.com').
        expected_title: Expected page title substring for validation (default: 'Google').
        **kwargs: Additional keyword arguments forwarded to build_request (e.g., headers, cookies, etc).

    Returns:
        True if the proxy returns the expected title and status code 200, False otherwise.
    """
    try:
        response = await asyncio.to_thread(
            build_request, endpoint=url, proxy=proxy, timeout=timeout, **kwargs
        )
        soup = BeautifulSoup(response.text, "html.parser")
        title = str(soup.title.string) if soup.title else ""
        if expected_title.lower() not in title.lower():
            print(f"{magenta(proxy)} returned {red('unexpected title:')} {cyan(title)}")
            return False
        return getattr(response, "status_code", None) == 200
    except Exception as e:
        print(f"{red('Error checking')} {magenta(proxy)} for {cyan(url)}: {e}")
        return False


device_ip = get_device_ip()


async def check_proxy_applied(proxy: str, timeout: int = 10, **kwargs) -> bool:
    """
    Check if a proxy is applied by requesting multiple public IP echo services and comparing the returned IP to the device IP.

    Args:
        proxy: Proxy string to use for the request. Accepted formats:
            - 'IP:PORT'
            - 'user:pass@IP:PORT'
            - 'IP:PORT@user:pass'
        timeout: Request timeout in seconds.
        **kwargs: Additional keyword arguments forwarded to build_request (e.g., headers, cookies, etc).

    Returns:
        True if any endpoint returns an IP different from the device IP (proxy applied), False otherwise.
    """
    # Use multiple public endpoints and extract the first IPv4 from the
    # response (same approach as packages/proxy-hunter-python/tests/build_request_direct.py)
    endpoints = [
        "http://httpbin.org/ip",
        "http://api.ipify.org?format=json",
        "http://icanhazip.com",
        "http://checkip.amazonaws.com",
        "https://httpbin.org/ip",
        "https://api.ipify.org?format=json",
        "https://icanhazip.com",
        "https://checkip.amazonaws.com",
    ]
    any_success = False
    for ep in endpoints:
        try:
            response = await asyncio.to_thread(
                build_request, endpoint=ep, proxy=proxy, timeout=timeout, **kwargs
            )
            if getattr(response, "status_code", None) and response.status_code != 200:
                print(
                    f"{magenta(proxy)} returned status code {red(response.status_code)} for {cyan(ep)}"
                )
                continue
            # Prefer parsing JSON responses for known keys, otherwise fall back to text extraction
            resp_ip = None
            try:
                data = response.json()
                # httpbin returns {"origin": "1.2.3.4"} (sometimes comma-separated)
                if isinstance(data, dict):
                    if "ip" in data:
                        val = data.get("ip")
                    elif "origin" in data:
                        val = data.get("origin")
                    else:
                        val = None
                    if val:
                        # extract first IPv4 from the value
                        if isinstance(val, (list, tuple)):
                            val = ",".join(map(str, val))
                        val_str = str(val)
                        m = re.search(r"(\d{1,3}(?:\.\d{1,3}){3})", val_str)
                        resp_ip = m.group(1) if m else None
            except Exception:
                # not JSON or json parsing failed; fall back to raw text
                text = (
                    response.text
                    if hasattr(response, "text")
                    else getattr(response, "content", "")
                )
                if isinstance(text, bytes):
                    try:
                        text = text.decode("utf-8", errors="replace")
                    except Exception:
                        text = str(text)
                m = re.search(r"(\d{1,3}(?:\.\d{1,3}){3})", text)
                resp_ip = m.group(1) if m else None
            if resp_ip:
                any_success = True
                if device_ip and resp_ip == device_ip:
                    print(
                        f"{magenta(proxy)} {red('is not applied')} for {cyan(ep)} (response IP {cyan(resp_ip)} matches device IP {cyan(device_ip)})"
                    )
                    # this endpoint indicates proxy not applied; try others
                    continue
                else:
                    print(
                        f"{magenta(proxy)} {green('appears applied')} for {cyan(ep)} (response IP {cyan(resp_ip)} differs from device IP {cyan(device_ip)})"
                    )
                    return True
            else:
                print(
                    f"{magenta(proxy)} {red('did not return an IP')} when checking {cyan(ep)}"
                )
        except Exception as e:
            print(f"Request to {cyan(ep)} failed for {magenta(proxy)}: {e}")
            continue

    if any_success:
        print(
            f"{magenta(proxy)} {red('is not applied')} (all endpoints matched device IP)"
        )
        return False
    # No endpoint returned an IP we could use
    print(
        f"Could not verify {magenta(proxy)} application {red('(no usable IPs returned)')}"
    )
    return False


async def _worker_check(
    data: dict,
    db: ProxyDB,
    device_ip: Optional[str],
    tested_proxies: Set[str],
    tested_lock: asyncio.Lock,
    semaphore: asyncio.Semaphore,
):
    async with semaphore:
        original_proxy = normalize_proxy_value(data)
        protocols = ["socks5", "socks4", "http"]
        for proto in protocols:
            proxy = f"{proto}://{original_proxy}"
            print(f"Checking {cyan(proxy)}...")
            url = "https://www.yahoo.com/"
            expected_title = "Yahoo"

            supports_https = await check_proxy_https(
                proxy=proxy, url=url, expected_title=expected_title
            )
            async with tested_lock:
                tested_proxies.add(proxy)

            if not supports_https:
                print(
                    f"{magenta(proxy)} does {red('not support HTTPS')} (failed to access {url})"
                )
                # mark https=false
                try:
                    db.update_data(data["proxy"], {"https": "false"}, False)
                except Exception:
                    pass
                continue

            proxy_applied = await check_proxy_applied(proxy=proxy)

            if not proxy_applied:
                print(
                    f"{magenta(proxy)} {green('supports HTTPS')} but is {red('not applied!')} Device IP: {cyan(device_ip)}"
                )
                continue

            # success
            print(f"{magenta(proxy)} {green('supports HTTPS and is applied!')}")
            try:
                db.update_data(
                    data["proxy"],
                    {
                        "status": "active",
                        "type": proto,
                        "https": "true",
                        "last_checked": get_current_rfc3339_time(),
                    },
                )
            except Exception:
                pass
            break


async def main(args):
    try:
        db = init_db("mysql")
    except Exception:
        db = init_readonly_db()

    def custom_filter(rows: List[dict[str, Any]]) -> List[dict[str, Any]]:
        filtered = []
        for row in rows:
            if row.get("https", "").lower() == "true":
                continue
            last_checked = row.get("last_checked", "")
            if not last_checked:
                filtered.append(row)
            if is_date_rfc3339_older_than(last_checked, hours=24):
                continue
        return filtered

    result: ProxyRetrievalResult = retrieve_proxies(
        db=db, limit=args.limit, custom_filter=custom_filter
    )

    proxies = result.proxies[: args.limit]
    source_label = result.source_label
    source_file = result.source_file

    random.shuffle(proxies)
    print("INFO", f"Proxy source: {source_label} ({len(proxies)} candidates)")

    tested_proxies: Set[str] = set()
    tested_lock = asyncio.Lock()

    concurrency = args.concurrency if args.concurrency and args.concurrency > 0 else 3
    semaphore = asyncio.Semaphore(concurrency)

    tasks = [
        _worker_check(data, db, device_ip, tested_proxies, tested_lock, semaphore)
        for data in proxies
    ]

    if tasks:
        await asyncio.gather(*tasks)

    try:
        db.close()
    except Exception:
        pass

    print(
        f"Finished testing {len(tested_proxies)} proxies from source {cyan(source_label)}."
    )
    if "file" in source_label.lower() and source_file and tested_proxies:
        try:
            remove_string_from_file(get_relative_path(source_file), tested_proxies)
        except Exception as e:
            print(f"[WARN] Failed removing tested proxies from file: {e}")


if __name__ == "__main__":
    args = parse_args(default_limit=1)

    # use --fileLock override when provided, otherwise --uid for lock filename
    file_lock_arg = getattr(args, "file_lock", None)
    if file_lock_arg:
        lock_path = file_lock_arg
    else:
        lock_name = args.uid if args.uid else current_filename
        lock_path = get_relative_path(f"tmp/locks/{lock_name}.lock")

    locker = FileLockHelper(lock_path)

    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    try:
        asyncio.run(main(args))
    finally:
        if locker:
            locker.unlock()
