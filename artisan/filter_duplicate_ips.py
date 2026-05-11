import json
import os
import sys
import asyncio
import random
from typing import Any, Dict, List, Optional, Set

from bs4 import BeautifulSoup
from proxy_hunter import build_request, get_device_ip, read_file, write_json

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src import ProxyDB
from src.func import get_relative_path
from src.func_console import cyan, red, magenta, green, yellow
from src.utils.file import remove_string_from_file
from src.utils.file.FileLockHelper import FileLockHelper
from artisan.proxy_getter import (
    normalize_proxy_value,
    retrieve_proxies,
    ProxyRetrievalResult,
)
from artisan.proxy_https_checker import check_proxy_applied, check_proxy_https
from src.utils.parse_args import parse_args
from src.func_date import get_current_rfc3339_time, is_date_rfc3339_older_than
from src.shared import init_db, init_readonly_db


async def check_proxy_http(
    proxy: str,
    timeout: int = 10,
    url: str = "http://httpforever.com/",
    expected_title: str = "HTTP Forever",
    **kwargs,
) -> bool:
    """
    Async wrapper: run blocking `build_request` in a thread and validate the page title for HTTP proxies.

    Args:
        proxy: Proxy string to use for the request (e.g., 'http://user:pass@host:port').
        timeout: Request timeout in seconds.
        url: The HTTP URL to check (default: 'http://httpforever.com/').
        expected_title: Expected page title substring for validation (default: 'HTTP Forever').
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
            print(
                f"{magenta(proxy)} returned unexpected title: {yellow(title)} (expected to contain '{yellow(expected_title)}')"
            )
            return False
        return getattr(response, "status_code", None) == 200
    except Exception as e:
        print(f"Error checking {magenta(proxy)} for {yellow(url)}: {e}")
        return False


def get_duplicates_ip_proxy(
    db: ProxyDB, per_page: int = 1000
) -> Dict[str, List[Dict[str, Any]]]:
    """Return a mapping of IP -> list of proxy rows for IPs with duplicates.

    Uses a DB-side GROUP BY + HAVING query to find duplicated IPs, then
    retrieves rows per IP in pages to avoid loading the entire table into memory.
    """
    db_helper = db.get_db()
    cacheFile: Optional[str] = None
    if db_helper:
        checksum = db_helper.checksum("proxies", ["proxy"])
        cacheFile = get_relative_path(
            f"tmp/proxies/{db.driver}_{checksum}_duplicate_ips_cache.txt"
        )
        if os.path.exists(cacheFile):
            print(f"Loading duplicate IPs from cache: {cyan(cacheFile)}")
            content = read_file(cacheFile)
            if content:
                try:
                    return json.loads(content)
                except Exception as e:
                    print(red(f"Failed to parse cache file {cacheFile}: {e}"))

    is_mysql = db.driver == "mysql"

    substr_function = (
        "SUBSTRING_INDEX(proxy, ':', 1)"
        if is_mysql
        else "CASE WHEN INSTR(proxy, ':') > 0 THEN SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) ELSE proxy END"
    )
    # Use deterministic ordering and simple grouping to find duplicate IPs faster
    placeholder = "%s" if is_mysql else "?"

    sql_duplicate_ips = f"""
    SELECT ip, COUNT(*) AS count_duplicates
        FROM (
            SELECT {substr_function} AS ip
            FROM proxies
        ) AS filtered_proxies
        GROUP BY ip
        HAVING COUNT(*) > 1
        LIMIT {placeholder} OFFSET {placeholder}
    """

    offset = 0
    ip_to_proxies: Dict[str, List[Dict[str, Any]]] = {}

    # Require an initialized DB helper for SQL operations.
    if not db_helper:
        print(
            red(
                "Database helper not initialized (db.get_db() is None). Exiting duplicate detection."
            )
        )
        return {}

    while True:
        rows: List[Dict[str, Any]] = []
        try:
            res = db_helper.execute_query_fetch(sql_duplicate_ips, (per_page, offset))
            rows = res if isinstance(res, list) else []
        except Exception:
            rows = []

        if not isinstance(rows, list) or not rows:
            break

        # Batch fetch proxies for all IPs returned in this page to reduce roundtrips
        ips: List[str] = []
        for r in rows:
            if isinstance(r, dict):
                v = r.get("ip")
                if v:
                    ips.append(v)

        if not ips:
            if len(rows) < per_page:
                break
            offset += per_page
            continue

        # build placeholders for IN clause
        in_placeholders = ", ".join([placeholder] * len(ips))
        sql_proxies_batch = f"""
        SELECT id, proxy, status, {substr_function} AS ip
        FROM proxies
        WHERE {substr_function} IN ({in_placeholders})
        ORDER BY ip, id
        """
        try:
            res_batch: Any = db_helper.execute_query_fetch(
                sql_proxies_batch, tuple(ips)
            )
            batch_rows = res_batch if isinstance(res_batch, list) else []
        except Exception:
            batch_rows = []

        for prow in batch_rows:
            if not isinstance(prow, dict):
                continue
            ip_val = prow.get("ip")
            if not ip_val:
                continue
            ip_to_proxies.setdefault(ip_val, []).append(prow)

        if len(rows) < per_page:
            break
        offset += per_page

    # Save to cache file if we have a DB connection and cache path
    if cacheFile:
        write_json(cacheFile, ip_to_proxies)

    return ip_to_proxies


async def run_checks_for_proxies(
    proxies: List[Dict[str, Any]], concurrency: int = 8, timeout: int = 10
):
    sem = asyncio.Semaphore(concurrency)

    async def worker(entry: Dict[str, Any]):
        proxy_val = str(entry.get("proxy") or "").strip()
        if not proxy_val:
            return entry, {
                "proxy": None,
                "type": None,
                "https": False,
                "applied": False,
            }
        async with sem:
            original = normalize_proxy_value(entry)
            protocols = ["socks5", "socks4", "http"]
            for proto in protocols:
                proxy_url = f"{proto}://{original}"
                applied = False
                protocol_ok = False
                supports_https = False

                protocol_ok = await check_proxy_http(
                    proxy=proxy_url,
                    timeout=timeout,
                    url="http://httpforever.com/",
                    expected_title="HTTP Forever",
                )
                if protocol_ok:
                    supports_https = await check_proxy_https(
                        proxy=proxy_url,
                        timeout=timeout,
                        url="https://www.yahoo.com/",
                        expected_title="Yahoo",
                    )
                    applied = await check_proxy_applied(
                        proxy=proxy_url, timeout=timeout
                    )

                if protocol_ok or applied:
                    return entry, {
                        "proxy": proxy_val,
                        "type": proto,
                        "https": supports_https,
                        "applied": bool(applied),
                        "protocol_ok": bool(protocol_ok),
                    }

            return entry, {
                "proxy": proxy_val,
                "type": None,
                "https": False,
                "applied": False,
                "protocol_ok": False,
            }

    tasks = [asyncio.create_task(worker(e)) for e in proxies]
    return await asyncio.gather(*tasks)


if __name__ == "__main__":
    args = parse_args()
    # Setup file lock (prevents concurrent runs)
    file_lock_arg = getattr(args, "file_lock", None)
    if file_lock_arg:
        locker = FileLockHelper(file_lock_arg)
    else:
        lock_name = getattr(args, "uid", None) or os.path.basename(__file__)
        locker = FileLockHelper(get_relative_path(f"tmp/locks/{lock_name}.lock"))
    if not locker.lock():
        print(red("Another instance is running. Exiting."))
        sys.exit(0)

    try:
        db = init_db()
    except Exception:
        db = init_readonly_db()

    try:
        # Use helper to get duplicates mapping (DB-backed, memory-friendly)
        per_page = 1000

        ip_to_proxies = get_duplicates_ip_proxy(db, per_page=per_page)
        # Shuffle both the IPs (keys) and the proxies (values)
        ip_items = list(ip_to_proxies.items())
        random.shuffle(ip_items)
        # Limit the number of IPs if args.limit > 0
        if args.limit > 0:
            ip_items = ip_items[: args.limit]
        ip_to_proxies = dict(ip_items)
        for proxies in ip_to_proxies.values():
            random.shuffle(proxies)
        print(f"Found {magenta(len(ip_to_proxies))} IPs with duplicates.")

        for ip, proxies in ip_to_proxies.items():
            print(f"Processing {magenta(ip)} with {cyan(len(proxies))} proxies")
            results = asyncio.run(
                run_checks_for_proxies(proxies, concurrency=8, timeout=10)
            )

            # results: list of (entry, result_dict)
            working = [
                entry
                for entry, res in results
                if res.get("protocol_ok") or res.get("https") or res.get("applied")
            ]
            failed = [
                entry
                for entry, res in results
                if not (
                    res.get("protocol_ok") or res.get("https") or res.get("applied")
                )
            ]

            # map proxy string -> successful protocol for updates
            protocol_map: Dict[str, Optional[str]] = {}
            for entry, res in results:
                p = entry.get("proxy") if isinstance(entry, dict) else None
                if p and (res.get("https") or res.get("applied")):
                    protocol_map[str(p).strip()] = res.get("type")

            print(f"  Working: {green(len(working))}")
            print(f"  Failed: {red(len(failed))}")

            if len(failed) > 0 and len(working) == 0:
                # If all proxies for this IP failed, we can consider them all inactive and delete them (leave 1 proxy in database)
                print(
                    f"  Keeping {cyan(failed[0].get('proxy'))} failed proxies for IP {magenta(ip)}."
                )
                for entry in failed[:-1]:  # Keep one entry to avoid deleting all
                    proxy_str = entry.get("proxy")
                    if proxy_str:
                        try:
                            db.remove(proxy_str)
                            print(f"    Deleted: {red(proxy_str)}")
                        except Exception as e:
                            print(f"    Failed to delete {proxy_str}: {e}")
            elif len(working) > 0:
                print(green(f"  Keeping {len(working)} working proxies for IP {ip}"))
                for entry in working:
                    proxy_str = entry.get("proxy")
                    if not proxy_str:
                        continue
                    proto = protocol_map.get(str(proxy_str).strip())
                    print(
                        f"    Keeping: {green(proxy_str)} (protocol: {cyan(str(proto)) if proto else 'unknown'})"
                    )
                    data_update = {
                        "status": "active",
                        "last_check": get_current_rfc3339_time(),
                    }
                    if proto:
                        data_update["type"] = proto
                    if entry.get("https"):
                        data_update["https"] = "true"
                    try:
                        db.update_data(proxy=proxy_str, data=data_update)
                    except Exception as e:
                        print(red(f"    Failed to update DB for {proxy_str}: {e}"))

                for entry in failed:
                    proxy_str = entry.get("proxy")
                    if not proxy_str:
                        continue
                    try:
                        db.remove(proxy_str)
                        print(f"    Deleted: {red(proxy_str)}")
                    except Exception as e:
                        print(f"    Failed to delete {proxy_str}: {e}")
    finally:
        try:
            db.close()
        except Exception:
            pass
        locker.unlock()
