import os
import sys
import asyncio
import random
import re
from typing import Any, Dict, List, Optional, Set, Tuple

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
from src.utils.parse_args import ParseArgs, parse_args
from src.func_date import (
    get_current_rfc3339_time,
    get_yesterday_rfc3339_time,
    is_date_rfc3339_older_than,
)
from src.shared import init_readonly_db
from artisan.proxy_https_checker import check_proxy_https, check_proxy_applied
from artisan.filter_duplicate_ips import check_proxy_http

concurrency = 4
timeout = 10


async def run_checks_for_proxies(
    args: ParseArgs,
) -> List[Tuple[Dict[str, Any], Dict[str, Any]]]:
    db = init_readonly_db()

    def custom_filter(rows: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        return [
            row
            for row in rows
            if isinstance(row, dict)
            and (row.get("https") or "").lower() != "true"
            and (
                not row.get("last_check")
                or is_date_rfc3339_older_than(row.get("last_check"), hours=24)
            )
        ]

    limit = args.limit if hasattr(args, "limit") and args.limit > 0 else 100
    proxies = custom_filter(db.get_untested_proxies(limit=limit))
    if not proxies:
        proxies = custom_filter(db.get_all_proxies(limit=limit))

    sem = asyncio.Semaphore(concurrency)

    async def worker(entry: Dict[str, Any]):
        proxy_val = str(entry.get("proxy") or "").strip()
        if not proxy_val:
            return entry, {
                "proxy": None,
                "type": None,
                "https": False,
                "applied": False,
                "status": "dead",
                "result": False,
                "private": False,
            }
        async with sem:
            original = normalize_proxy_value(entry)
            protocols = ["socks5", "socks4", "http"]
            private_seen = False
            for proto in protocols:
                proxy_url = f"{proto}://{original}"
                applied = False
                http_check = {"result": False, "private": False}
                supports_https = False

                http_check = await check_proxy_http(
                    proxy=proxy_url,
                    timeout=timeout,
                    url="http://httpforever.com/",
                    expected_title="HTTP Forever",
                )
                protocol_result = http_check.get("result", False)
                is_private = http_check.get("private", False)
                private_seen = private_seen or bool(is_private)
                if protocol_result:
                    supports_https = await check_proxy_https(
                        proxy=proxy_url,
                        timeout=timeout,
                        url="https://www.yahoo.com/",
                        expected_title="Yahoo",
                    )
                    applied = await check_proxy_applied(
                        proxy=proxy_url, timeout=timeout
                    )

                if protocol_result or applied:
                    return entry, {
                        "proxy": proxy_val,
                        "type": proto,
                        "https": supports_https,
                        "applied": bool(applied),
                        "protocol_ok": bool(protocol_result),
                        "status": "active",
                        "result": bool(protocol_result),
                        "private": bool(private_seen),
                    }

            return entry, {
                "proxy": proxy_val,
                "type": None,
                "https": False,
                "applied": False,
                "protocol_ok": False,
                "status": "dead",
                "result": False,
                "private": bool(private_seen),
            }

    tasks = [asyncio.create_task(worker(e)) for e in proxies]
    return await asyncio.gather(*tasks)


async def main(args):
    print("Starting CI checker...")
    results = await run_checks_for_proxies(args)
    db = init_readonly_db()

    for entry, result in results:
        proxy_val = str(result.get("proxy") or "").strip()
        if not proxy_val:
            continue

        update_data = {"status": result.get("status") or "dead"}
        update_data["private"] = "true" if result.get("private") else "false"
        if result.get("status") == "active":
            if result.get("type"):
                update_data["type"] = result.get("type")
            if result.get("https"):
                update_data["https"] = "true"
            if result.get("private"):
                update_data["private"] = "true"

        try:
            db.update_data(
                proxy=proxy_val,
                data=update_data,
                update_time=True,
                debug=True,
            )
        except Exception as e:
            print(f"Error updating proxy {proxy_val}: {e}")

    print(f"Checked {len(results)} proxies")

    # Count working proxies
    working_count = 0
    for entry, result in results:
        if result.get("result") or result.get("https") or result.get("applied"):
            working_count += 1

    print(f"Working proxies: {working_count}")


if __name__ == "__main__":
    args = parse_args(default_limit=1)
    asyncio.run(main(args))
