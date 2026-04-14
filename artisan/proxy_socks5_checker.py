import os
import asyncio
import shutil
import platform
import subprocess
import time
import signal
import sys
import random
import re
from datetime import datetime, timezone
from typing import Any, Callable, Iterable, List, Optional
from urllib.parse import urlsplit
from proxy_hunter import build_request
from colorama import Fore, Style, just_fix_windows_console

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from src.utils.file.FileLockHelper import FileLockHelper
from artisan.proxy_getter import (
    parse_args,
    load_proxies_from_file,
    load_proxies_from_cli,
)
from src.shared import init_db

just_fix_windows_console()
COLOR_ENABLED = True

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


def color_proxy_text(value: str) -> str:
    if not COLOR_ENABLED:
        return value
    return f"{Fore.CYAN}{value}{Style.RESET_ALL}"


def color_status_text(message: str) -> str:
    if not COLOR_ENABLED:
        return message

    def replacer(match: re.Match[str]) -> str:
        word = match.group(0)
        lowered = word.lower()
        if lowered.startswith("fail"):
            return f"{Fore.RED}{word}{Style.RESET_ALL}"
        return f"{Fore.GREEN}{word}{Style.RESET_ALL}"

    return re.sub(
        r"\b(pass|passed|success|succeed|succeeded|ok|fail|failed)\b",
        replacer,
        message,
        flags=re.IGNORECASE,
    )


def print_status(tag: str, message: str, proxy: Optional[str] = None) -> None:
    proxy_text = f" {color_proxy_text(proxy)}" if proxy else ""
    print(f"[{tag}]{proxy_text} {color_status_text(message)}".rstrip())


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

        if "://" in host_port:
            host_port = host_port.split("://", 1)[1]

        normalized.append(f"socks5://{host_port}")

    return normalized


def to_proxy_rows(items: Iterable[Any]) -> List[dict[str, Any]]:
    """Map raw proxy inputs into lightweight rows."""
    rows: List[dict[str, Any]] = []

    for item in items:
        proxy_value: Optional[str] = None
        last_check: Optional[Any] = None

        if isinstance(item, str):
            proxy_value = item.strip()
        elif isinstance(item, dict):
            proxy_value = str(item.get("proxy") or "").strip()
            last_check = item.get("last_check")
            if not proxy_value and item.get("ip") and item.get("port"):
                proxy_value = f"{item['ip']}:{item['port']}"
        elif isinstance(item, (tuple, list)) and len(item) >= 2:
            proxy_value = f"{item[0]}:{item[1]}"

        if not proxy_value:
            continue

        if proxy_value.startswith("socks5://"):
            proxy_value = proxy_value.replace("socks5://", "", 1)
        elif "://" in proxy_value:
            proxy_value = proxy_value.split("://", 1)[1]

        row = {"proxy": proxy_value, "last_check": last_check}
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

        proxies: List[dict[str, Any]] = []
        source_label = "db"

        cli_rows = to_proxy_rows(load_proxies_from_cli())
        if len(cli_rows) != 0:
            proxies = cli_rows
            source_label = "cli"

        if not proxies:
            file_rows = to_proxy_rows(load_proxies_from_file(proxy_file))
            if file_rows:
                proxies = file_rows
                source_label = "file"

        if not proxies:
            proxies = to_proxy_rows(
                db.get_all_proxies(
                    limit=args.limit,
                    randomize=True
                )
            )
            source_label = "db"

        proxies = [
            proxy
            for proxy in proxies
            if isinstance(proxy, dict)
            and is_last_check_before_today(proxy.get("last_check"))
        ]
        random.shuffle(proxies)
        print(f"[INFO] Proxy source: {source_label} ({len(proxies)} candidates)")

        def on_success(proxy, _):
            db.update_data(
                proxy, {"type": "socks5", "status": "active", "https": "true"}
            )

        def on_failed(proxy, _):
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
            db.update_data(proxy, {"type": "-".join(type_parts)})

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

        tested_set = {
            (
                proxy.replace("socks5://", "", 1)
                if proxy.startswith("socks5://")
                else proxy
            )
            for proxy in proxy_candidates
        }
        if source_label == "file" and tested_set and os.path.isfile(proxy_file):
            with open(proxy_file, "r", encoding="utf-8") as f:
                file_lines = f.readlines()

            kept_lines: List[str] = []
            removed_count = 0
            for line in file_lines:
                raw = line.strip()
                normalized = (
                    raw.replace("socks5://", "", 1)
                    if raw.startswith("socks5://")
                    else raw
                )
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

        db.close()
    finally:
        if locker:
            locker.unlock()
