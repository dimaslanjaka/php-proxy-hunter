import os
import asyncio
import sys
import random
import re
from datetime import datetime, timezone
from typing import Any, Callable, Iterable, List, Optional
from urllib.parse import urlsplit
from proxy_hunter import build_request

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from src.func_console import cyan, red
from src.database.SQLiteMarker import SQLiteMarker
from src.utils.file.FileLockHelper import FileLockHelper
from artisan.proxy_getter import (
    load_proxies_from_file,
    load_proxies_from_cli,
)
from src.utils.parse_args import parse_args
from src.shared import init_db

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


def normalize_proxy_value(value: str) -> str:
    text = value.strip()
    if text.startswith("socks5://"):
        return text.replace("socks5://", "", 1)
    if "://" in text:
        return text.split("://", 1)[1]
    return text


def print_status(tag: str, message: str, proxy: Optional[str] = None) -> None:
    display_tag = tag
    if tag == "SOCKS5 FAIL":
        display_tag = "FAIL"
    elif tag == "SOCKS5 OK":
        display_tag = "OK"

    def colorize_fail_word(text: str) -> str:
        return re.sub(r"\bfail\b", lambda m: red(m.group(0)), text, flags=re.IGNORECASE)

    def colorize_proxy_endpoint(text: str) -> str:
        value = text.strip()
        if not value:
            return value
        if "://" in value:
            _scheme, endpoint = value.split("://", 1)
            return cyan(endpoint)
        return cyan(value)

    formatted_message = colorize_fail_word(message)
    proxy_text = f" {colorize_proxy_endpoint(proxy)}" if proxy else ""
    print(f"[{display_tag}]{proxy_text} {formatted_message}".rstrip())


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

        return parsed.hostname, int(parsed.port), parsed.username, parsed.password
    except Exception:
        return None


def to_socks5_list(items: Iterable[Any]) -> List[str]:
    normalized: List[str] = []

    for item in items:
        host_port: Optional[str] = None

        if isinstance(item, str):
            host_port = item.strip()
        elif isinstance(item, dict):
            if item.get("proxy"):
                host_port = str(item["proxy"]).strip()
            elif item.get("ip") and item.get("port"):
                host_port = f"{item['ip']}:{item['port']}"
        elif isinstance(item, (tuple, list)) and len(item) >= 2:
            host_port = f"{item[0]}:{item[1]}"

        if not host_port:
            continue

        if host_port.startswith("socks5://"):
            normalized.append(host_port)
            continue

        normalized.append(f"socks5://{normalize_proxy_value(host_port)}")

    return normalized


def to_proxy_rows(items: Iterable[Any]) -> List[dict[str, Any]]:
    """Map raw proxy inputs into lightweight rows."""
    rows: List[dict[str, Any]] = []

    for item in items:
        proxy_value: Optional[str] = None
        row: dict[str, Any] = {}

        if isinstance(item, str):
            proxy_value = item.strip()
        elif isinstance(item, dict):
            proxy_value = str(item.get("proxy") or "").strip()
            row = {
                "type": item.get("type"),
                "status": item.get("status"),
                "https": item.get("https"),
                "last_check": item.get("last_check"),
            }
            if not proxy_value and item.get("ip") and item.get("port"):
                proxy_value = f"{item['ip']}:{item['port']}"
        elif isinstance(item, (tuple, list)) and len(item) >= 2:
            proxy_value = f"{item[0]}:{item[1]}"

        if not proxy_value:
            continue

        proxy_value = normalize_proxy_value(proxy_value)
        row["proxy"] = proxy_value
        rows.append(row)

    return rows


def is_last_check_before_today(last_check: Any) -> bool:
    """Keep proxies checked before today; keep missing/invalid timestamps as fallback."""
    if last_check is None:
        return True

    text = str(last_check).strip()
    if not text:
        return True

    if text.endswith("Z"):
        text = f"{text[:-1]}+00:00"

    try:
        checked_at = datetime.fromisoformat(text)
    except ValueError:
        return True

    if checked_at.tzinfo is None:
        checked_at = checked_at.replace(tzinfo=timezone.utc)

    checked_date_utc = checked_at.astimezone(timezone.utc).date()
    today_utc = datetime.now(timezone.utc).date()
    return checked_date_utc < today_utc


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
        result = test_socks5_proxy(host, port, username, password, timeout=timeout)

        if result["success"]:
            passed.append(proxy)
            print_status("SOCKS5 OK", "pass", proxy)
            if on_success is not None:
                try:
                    on_success(proxy, result)
                except Exception as e:
                    print_status("WARN", f"on_success callback failed: {e}", proxy)
            if return_early_first_working:
                print("[INFO] Returning early on first working SOCKS5 proxy")
                return passed
        else:
            print_status("SOCKS5 FAIL", f"fail -> {result['error']}", proxy)
            if on_failed is not None:
                try:
                    on_failed(proxy, result)
                except Exception as e:
                    print_status("WARN", f"on_failed callback failed: {e}", proxy)

    print(f"[INFO] SOCKS5 pre-check passed: {len(passed)}/{len(proxies)}")
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

    async def check_one(proxy: str) -> Optional[tuple[str, bool]]:
        parsed = parse_socks5_proxy(proxy)
        if not parsed:
            print_status("SKIP", "Invalid socks5 proxy format", proxy)
            return None

        host, port, username, password = parsed
        async with semaphore:
            result = await asyncio.to_thread(
                test_socks5_proxy,
                host,
                port,
                username,
                password,
                timeout,
            )

        if result["success"]:
            print_status("SOCKS5 OK", "pass", proxy)
            if on_success is not None:
                try:
                    on_success(proxy, result)
                except Exception as e:
                    print_status("WARN", f"on_success callback failed: {e}", proxy)
            return proxy, True

        print_status("SOCKS5 FAIL", f"fail -> {result['error']}", proxy)
        if on_failed is not None:
            try:
                on_failed(proxy, result)
            except Exception as e:
                print_status("WARN", f"on_failed callback failed: {e}", proxy)
        return proxy, False

    tasks = [asyncio.create_task(check_one(proxy)) for proxy in proxies]
    try:
        for done in asyncio.as_completed(tasks):
            outcome = await done
            if not outcome:
                continue

            proxy, success = outcome
            if success:
                passed.append(proxy)
                if return_early_first_working:
                    print("[INFO] Returning early on first working SOCKS5 proxy")
                    for task in tasks:
                        if not task.done():
                            task.cancel()
                    break
    finally:
        await asyncio.gather(*tasks, return_exceptions=True)

    print(f"[INFO] SOCKS5 pre-check passed: {len(passed)}/{len(proxies)}")
    return passed


def filter_test_socks5_proxies_parallel(
    proxies: List[str],
    timeout: int = 5,
    on_success: Optional[Callable[[str, dict[str, Any]], None]] = None,
    on_failed: Optional[Callable[[str, dict[str, Any]], None]] = None,
    return_early_first_working: bool = False,
    concurrency: int = 3,
) -> List[str]:
    """Sync wrapper around the async parallel SOCKS5 checker."""
    return asyncio.run(
        filter_test_socks5_proxies_async(
            proxies=proxies,
            timeout=timeout,
            on_success=on_success,
            on_failed=on_failed,
            return_early_first_working=return_early_first_working,
            concurrency=concurrency,
        )
    )


if __name__ == "__main__":
    args = parse_args()

    # Create and acquire file lock
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    try:
        proxy_file = get_relative_path("proxies.txt")
        db = init_db("mysql")
        marker = SQLiteMarker(
            db_filename="proxy_socks5_checker.sqlite",
            table_name="checked_proxies",
            key_column="proxy",
            base_dir="tmp/database",
        )

        try:
            proxies: List[dict[str, Any]] = []
            source_label = "db"

            cli_rows = to_proxy_rows(load_proxies_from_cli())
            if len(cli_rows) != 0:
                proxies = cli_rows
                # If CLI provided a file via --file, resolve and assign it to
                # `proxy_file` so later removal uses the same file path.
                if getattr(args, "proxy_file", None) and args.proxy_file:
                    proxy_file = (
                        args.proxy_file
                        if os.path.exists(args.proxy_file)
                        else get_relative_path(args.proxy_file)
                    )
                    source_label = f"file://{proxy_file}"
                else:
                    source_label = "cli"

            if not proxies:
                file_rows = to_proxy_rows(load_proxies_from_file(proxy_file))
                if file_rows:
                    proxies = file_rows
                    # Mark file-loaded sources with the resolved path so cleanup runs
                    source_label = f"file://{proxy_file}"

            if not proxies:
                proxies = to_proxy_rows(
                    db.get_all_proxies(limit=args.limit, randomize=True)
                )
                source_label = "db"

            proxies = [
                proxy
                for proxy in proxies
                if isinstance(proxy, dict)
                and is_last_check_before_today(proxy.get("last_check"))
            ]

            proxy_by_key: dict[str, dict[str, Any]] = {}
            ordered_keys: List[str] = []
            for proxy in proxies:
                proxy_value = str(proxy.get("proxy") or "").strip()
                if not proxy_value:
                    continue

                marker_key = normalize_proxy_value(proxy_value)
                if marker_key in proxy_by_key:
                    continue

                proxy_by_key[marker_key] = proxy
                ordered_keys.append(marker_key)

            pending_keys, already_checked = marker.filter_unseen(ordered_keys)
            proxies = [proxy_by_key[key] for key in pending_keys]

            random.shuffle(proxies)
            print(
                f"[INFO] Proxy source: {source_label} ({len(proxies)} candidates); "
                f"already_checked={already_checked}"
            )

            proxy_row_map = {
                normalize_proxy_value(str(proxy["proxy"])): proxy
                for proxy in proxies
                if isinstance(proxy, dict) and proxy.get("proxy")
            }

            def on_success(proxy, _):
                db.update_data(
                    proxy, {"type": "socks5", "status": "active", "https": "true"}
                )

            def on_failed(proxy, _):
                row = proxy_row_map.get(normalize_proxy_value(proxy))
                if not isinstance(row, dict):
                    rows = db.select(proxy)
                    if not rows:
                        return

                    row = rows[0] if isinstance(rows, list) else rows
                    if not isinstance(row, dict):
                        return

                current_type = row.get("type")
                if current_type is None:
                    return

                type_parts = [
                    part
                    for part in str(current_type).split("-")
                    if part and part != "socks5"
                ]
                db.update_data(proxy, {"type": "-".join(type_parts)}, update_time=False)

            proxy_candidates = to_socks5_list(proxies[: args.limit])
            proxy_working = filter_test_socks5_proxies_parallel(
                proxy_candidates,
                timeout=60,
                on_success=on_success,
                on_failed=on_failed,
                return_early_first_working=False,
                concurrency=3,
            )
            print(
                f"[INFO] Tested {len(proxy_candidates)} proxies, passed {len(proxy_working)}"
            )

            tested_set = {normalize_proxy_value(proxy) for proxy in proxy_candidates}
            for tested_proxy in tested_set:
                marker.mark(tested_proxy)

            if source_label == "file" and tested_set and os.path.isfile(proxy_file):
                with open(proxy_file, "r", encoding="utf-8") as f:
                    file_lines = f.readlines()

                kept_lines: List[str] = []
                removed_count = 0
                for line in file_lines:
                    raw = line.strip()
                    normalized = normalize_proxy_value(raw)
                    if normalized in tested_set:
                        removed_count += 1
                        continue
                    kept_lines.append(line)

                trimmed_trailing_empty = 0
                while kept_lines and not kept_lines[-1].strip():
                    kept_lines.pop()
                    trimmed_trailing_empty += 1

                if removed_count or trimmed_trailing_empty:
                    with open(proxy_file, "w", encoding="utf-8") as f:
                        f.writelines(kept_lines)
                    print(
                        f"[INFO] Removed {removed_count} tested proxies from {proxy_file}; "
                        f"trimmed {trimmed_trailing_empty} trailing empty lines"
                    )
        finally:
            marker.close()
            db.close()
    finally:
        if locker:
            locker.unlock()
