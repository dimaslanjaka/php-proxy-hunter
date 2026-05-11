import os
import asyncio
import sys
import random
from typing import Any, Callable, Iterable, List, Optional
from urllib.parse import urlsplit
from proxy_hunter import build_request, extract_proxies

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from src.func_console import cyan, red, magenta, green
from src.utils.file.FileLockHelper import FileLockHelper
from src.utils.file import remove_string_from_file
from artisan.proxy_getter import (
    normalize_proxy_value,
    retrieve_proxies,
    ProxyRetrievalResult,
)
from src.utils.parse_args import parse_args
from src.func_date import get_yesterday_rfc3339_time, is_date_rfc3339_older_than
from src.shared import init_db

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


def print_status(tag: str, message: str, proxy: Optional[str] = None) -> None:
    display_tag = {"SOCKS5 FAIL": "FAIL", "SOCKS5 OK": "OK"}.get(tag, tag)

    def colorize_proxy_endpoint(text: Optional[str]) -> str:
        if not text:
            return str(text or "")
        value = text
        # Prefer extracting a clean host:port via extract_proxies when possible.
        parsed = extract_proxies(value)
        if parsed:
            proxy_val = str(getattr(parsed[0], "proxy", "") or "")
            if proxy_val:
                return magenta(proxy_val)

        # Fallback: color the raw value when parsing doesn't yield a clean proxy
        return magenta(value)

    formatted_message = message
    proxy_text = f" {colorize_proxy_endpoint(proxy)}" if proxy else ""
    # Color the display tag brackets: OK green, FAIL red
    if display_tag == "OK":
        colored_tag = green(display_tag)
    elif display_tag == "FAIL":
        colored_tag = red(display_tag)
    elif display_tag == "INFO":
        colored_tag = cyan(display_tag)
    else:
        colored_tag = display_tag

    print(f"[{colored_tag}]{proxy_text} {formatted_message}".rstrip())


def test_socks5_proxy(proxy_host, proxy_port, username=None, password=None, timeout=5):
    """
    Test a SOCKS5 proxy.

    Args:
        proxy_host (str): Proxy IP/domain
        proxy_port (int): Proxy port
        username (str, optional): Username for auth
        password (str, optional): Password for auth
        timeout (int): Timeout in seconds

    Returns:
        dict: {success: bool, status_code: int|None, ip: str|None, error: str|None}
    """
    proxy_target = f"{proxy_host}:{proxy_port}"

    try:
        r = build_request(
            proxy=proxy_target,
            proxy_type="socks5",
            proxy_username=username,
            proxy_password=password,
            endpoint="https://httpbin.org/ip",
            timeout=timeout,
            cookie_file=None,
            verify=True,
            no_cache=True,
        )
        return {
            "success": True,
            "status_code": r.status_code,
            "ip": r.json().get("origin"),
            "error": None,
        }
    except Exception as e:
        return {"success": False, "status_code": None, "ip": None, "error": str(e)}


def parse_socks5_proxy(
    proxy_url: str,
) -> Optional[tuple[str, int, Optional[str], Optional[str]]]:
    try:
        parsed = urlsplit(proxy_url)
        if parsed.scheme != "socks5" or not parsed.hostname or not parsed.port:
            return None
        return parsed.hostname, parsed.port, parsed.username, parsed.password
    except Exception:
        return None


def to_socks5_list(items: Iterable[Any]) -> List[str]:
    normalized: List[str] = []
    append = normalized.append

    for item in items:
        host_port = None

        if isinstance(item, str):
            host_port = item.strip()
        elif isinstance(item, dict):
            host_port = item.get("proxy") or (
                f"{item.get('ip')}:{item.get('port')}"
                if item.get("ip") and item.get("port")
                else None
            )
        elif isinstance(item, (tuple, list)) and len(item) >= 2:
            host_port = f"{item[0]}:{item[1]}"

        if not host_port:
            continue

        host_port = str(host_port).strip()
        if not host_port:
            continue

        if host_port.startswith("socks5://"):
            append(host_port)
        else:
            append(f"socks5://{normalize_proxy_value(host_port)}")

    return normalized


def filter_test_socks5_proxies(
    proxies: List[str],
    timeout: int = 5,
    on_success: Optional[Callable[[str, dict[str, Any]], None]] = None,
    on_failed: Optional[Callable[[str, dict[str, Any]], None]] = None,
    return_early_first_working: bool = False,
) -> List[str]:
    passed: List[str] = []

    for proxy in proxies:
        parsed = parse_socks5_proxy(proxy)
        if not parsed:
            print_status("SKIP", "Invalid socks5 proxy format", proxy)
            continue

        host, port, username, password = parsed
        print_status("INFO", f"Checking...", proxy)
        result = test_socks5_proxy(host, port, username, password, timeout)

        if result["success"]:
            passed.append(proxy)
            print_status("SOCKS5 OK", "pass", proxy)

            if on_success:
                try:
                    on_success(proxy, result)
                except Exception as e:
                    print_status("WARN", f"on_success callback failed: {e}", proxy)

            if return_early_first_working:
                print_status("INFO", "Returning early on first working SOCKS5 proxy")
                return passed
        else:
            print_status("SOCKS5 FAIL", f"-> {result['error']}", proxy)

            if on_failed:
                try:
                    on_failed(proxy, result)
                except Exception as e:
                    print_status("WARN", f"on_failed callback failed: {e}", proxy)

    print_status("INFO", f"SOCKS5 pre-check passed: {len(passed)}/{len(proxies)}")
    return passed


async def filter_test_socks5_proxies_async(
    proxies: List[str],
    timeout: int = 5,
    on_success: Optional[Callable[[str, dict[str, Any]], None]] = None,
    on_failed: Optional[Callable[[str, dict[str, Any]], None]] = None,
    return_early_first_working: bool = False,
    concurrency: int = 3,
) -> List[str]:
    """Async parallel SOCKS5 checker with bounded concurrency."""
    passed: List[str] = []
    semaphore = asyncio.Semaphore(max(1, concurrency))

    async def check_one(proxy: str):
        parsed = parse_socks5_proxy(proxy)
        if not parsed:
            print_status("SKIP", "Invalid socks5 proxy format", proxy)
            return None

        host, port, username, password = parsed

        async with semaphore:
            print_status("INFO", f"Checking {proxy}...", proxy)
            result = await asyncio.to_thread(
                test_socks5_proxy, host, port, username, password, timeout
            )

        if result["success"]:
            print_status("SOCKS5 OK", "pass", proxy)
            if on_success:
                try:
                    on_success(proxy, result)
                except Exception as e:
                    print_status("WARN", f"on_success callback failed: {e}", proxy)
            return proxy, True

        print_status("SOCKS5 FAIL", f"-> {result['error']}", proxy)
        if on_failed:
            try:
                on_failed(proxy, result)
            except Exception as e:
                print_status("WARN", f"on_failed callback failed: {e}", proxy)
        return proxy, False

    tasks = [asyncio.create_task(check_one(p)) for p in proxies]

    try:
        for fut in asyncio.as_completed(tasks):
            outcome = await fut
            if not outcome:
                continue

            proxy, success = outcome
            if success:
                passed.append(proxy)

                if return_early_first_working:
                    print_status(
                        "INFO", "Returning early on first working SOCKS5 proxy"
                    )
                    for t in tasks:
                        t.cancel()
                    break
    finally:
        await asyncio.gather(*tasks, return_exceptions=True)

    print_status("INFO", f"SOCKS5 pre-check passed: {len(passed)}/{len(proxies)}")
    return passed


def filter_test_socks5_proxies_parallel(**kwargs) -> List[str]:
    """Sync wrapper around the async parallel SOCKS5 checker."""
    return asyncio.run(filter_test_socks5_proxies_async(**kwargs))


if __name__ == "__main__":
    args = parse_args(default_limit=1)

    # Create and acquire file lock (allow override via --fileLock)
    file_lock_arg = getattr(args, "file_lock", None)
    if file_lock_arg:
        locker = FileLockHelper(file_lock_arg)
    else:
        locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    try:
        proxy_file = get_relative_path("proxies.txt")
        db = init_db("mysql")

        try:
            # Build custom filter that applies last-check + dedupe
            def custom_filter(rows: List[dict[str, Any]]) -> List[dict[str, Any]]:
                rows = [
                    r
                    for r in rows
                    if isinstance(r, dict)
                    and (
                        not r.get("last_check")
                        or is_date_rfc3339_older_than(r.get("last_check"), hours=24)
                    )
                ]

                proxy_by_key = {}
                for proxy in rows:
                    value = str(proxy.get("proxy") or "").strip()
                    if not value:
                        continue
                    key = normalize_proxy_value(value)
                    proxy_by_key.setdefault(key, proxy)

                return list(proxy_by_key.values())

            result: ProxyRetrievalResult = retrieve_proxies(
                db=db, limit=args.limit, custom_filter=custom_filter
            )

            proxies = result.proxies
            source_label = result.source_label

            random.shuffle(proxies)
            print_status(
                "INFO", f"Proxy source: {source_label} ({len(proxies)} candidates)"
            )

            proxy_row_map = {
                normalize_proxy_value(str(p["proxy"])): p
                for p in proxies
                if isinstance(p, dict) and p.get("proxy")
            }

            def on_success(proxy, _):
                db.update_data(
                    proxy, {"type": "socks5", "status": "active", "https": "true"}
                )

            def on_failed(proxy, _):
                row = proxy_row_map.get(normalize_proxy_value(proxy))
                if not isinstance(row, dict):
                    rows = db.select(proxy)
                    row = rows[0] if isinstance(rows, list) and rows else rows
                    if not isinstance(row, dict):
                        return

                current_type = row.get("type")
                if not current_type:
                    return

                new_type = "-".join(
                    part for part in str(current_type).split("-") if part != "socks5"
                )
                db.update_data(proxy, {"type": new_type}, update_time=False)

            proxy_candidates = to_socks5_list(proxies[: args.limit])

            proxy_working = filter_test_socks5_proxies_parallel(
                proxies=proxy_candidates,
                timeout=60,
                on_success=on_success,
                on_failed=on_failed,
                return_early_first_working=False,
                concurrency=3,
            )

            print_status(
                "INFO",
                f"Tested {len(proxy_candidates)} proxies, passed {len(proxy_working)}",
            )

            tested_keys = {
                normalize_proxy_value(str(p.get("proxy") or "").strip())
                for p in proxies[: args.limit]
                if isinstance(p, dict) and p.get("proxy")
            }

            # Marker functionality removed; no persistent marking performed

            # Prefer using the explicit `source_file` returned by `retrieve_proxies`.
            source_file = getattr(result, "source_file", None)

            candidate = None
            if source_file:
                candidate = source_file
            elif isinstance(source_label, str) and source_label.startswith("file://"):
                candidate = source_label.split("file://", 1)[1].lstrip("/")

            if candidate and tested_keys:
                target_file = candidate if os.path.isfile(candidate) else proxy_file

                if os.path.isfile(target_file):
                    # Remove occurrences using helper once for the full set
                    try:
                        remove_string_from_file(target_file, tested_keys)
                    except Exception as e:
                        print_status("WARN", f"Failed removing keys from file: {e}")

                    print_status(
                        "INFO",
                        f"Attempted removal of tested proxies from {target_file}",
                    )
                else:
                    print_status("INFO", f"Source file not found: {target_file}")
        finally:
            output_file = get_relative_path("working.json")
            db.get_working_proxies(
                output_file=output_file,
                last_checked=get_yesterday_rfc3339_time(),
                limit=1000,
            )
            print(f"Saved working proxies to {cyan(output_file)}")
            db.close()
    finally:
        if locker:
            locker.unlock()
