import os
import asyncio
import sys
import random
from typing import Any, List, Optional, Dict, cast
from proxy_hunter import build_request

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from src.func_console import cyan, red, magenta, yellow
from src.database.SQLiteMarker import SQLiteMarker
from src.utils.file.FileLockHelper import FileLockHelper
from artisan.proxy_getter import (
    normalize_proxy_value,
    retrieve_proxies,
    ProxyRetrievalResult,
)
from src.utils.parse_args import parse_args, ParseArgs
from src.ProxyDB import ProxyDB
from src.func_date import is_date_rfc3339_older_than
from src.shared import init_db
from bs4 import BeautifulSoup
from dataclasses import dataclass


# --- Data model ---
@dataclass
class FetchResult:
    title: Optional[str]
    proxy_type: Optional[str]
    proxy: str
    status_code: Optional[int] = None


# --- Helpers ---
def extract_title(html: Optional[str]) -> Optional[str]:
    if not html:
        return None
    soup = BeautifulSoup(html, "html.parser")
    t = soup.title
    return t.string.strip() if t and t.string else None


async def fetch_with_proxy(endpoint: str, proxy: str) -> FetchResult:
    proxy_types = ["socks5", "socks4", "http"]

    for ptype in proxy_types:
        response = None
        try:
            # run blocking request in thread (important)
            response = await asyncio.to_thread(
                build_request,
                endpoint=endpoint,
                proxy=proxy,
                proxy_type=ptype,
                no_cache=True,
            )
        except Exception as e:
            print(f"{yellow(f'[{ptype}]')} Failed {magenta(proxy)}: {e}")
            # on exception, skip to next proxy type
            continue
        else:
            # Only executed when no exception was raised. Handle response cases
            if response and getattr(response, "ok", False):
                title = extract_title(response.text)
                return FetchResult(
                    title=title,
                    proxy_type=ptype,
                    proxy=proxy,
                    status_code=getattr(response, "status_code", None),
                )

            # Non-exceptional non-OK response — log status for debugging
            try:
                status = getattr(response, "status_code", None)
                reason = getattr(response, "reason", "")
                print(
                    f"{yellow(f'[{ptype}]')} Failed {magenta(proxy)}: status={status} {reason}"
                )
                # Return status for caller to act on (e.g. mark private on 407)
                return FetchResult(
                    title=None, proxy_type=ptype, proxy=proxy, status_code=status
                )
            except Exception:
                pass

    return FetchResult(title=None, proxy_type=None, proxy=proxy)


# --- Utilities borrowed from proxy_socks5_checker ---
current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


async def run_checks(
    proxies: List[Dict[str, Any]],
    args: ParseArgs,
    db: ProxyDB,
) -> None:
    # Configs to check for each proxy
    config = {
        "httpOnly": {
            "endpoint": "http://httpforever.com/",
            "expected_title": "HTTP Forever",
        },
        "httpsOnly": {
            "endpoint": "https://www.ssl.org/certificate-key-matcher",
            "expected_title": "SSL certificate with Private Key/CSR matcher",
        },
    }

    # Iterate proxies first, then test each proxy against all configured endpoints.
    # If a proxy matches an endpoint's expected title, stop checking further proxies.
    for p in proxies[: args.limit]:
        if not p.get("proxy"):
            continue

        proxy: str = cast(str, p["proxy"])
        if p.get("username") and p.get("password"):
            proxy = f"{p['username']}:{p['password']}@{proxy}"
        print(f"Testing proxy: {magenta(p['proxy'])}")

        # Test this proxy against each configured endpoint
        for cfg_name, cfg in config.items():
            endpoint: str = cast(str, cfg.get("endpoint", ""))
            expected_title: str = cast(str, cfg["expected_title"])
            print(f"Checking config '{cyan(cfg_name)}': {endpoint}")

            result: FetchResult = await fetch_with_proxy(endpoint, proxy)

            # If proxy requires authentication (HTTP 407), mark as private and
            # skip further checks for this proxy.
            if result.status_code == 407:
                db.update_data(proxy=result.proxy, data={"private": "true"})
                break

            if result.title:
                if expected_title.lower() in result.title.lower():
                    print(
                        f"{yellow(f'[{result.proxy_type}]')} {magenta(result.proxy)} -> {result.title}"
                    )
                    db.update_data(proxy=result.proxy, data={"private": "false"})
                    # Found a working proxy for at least one config -> stop checking proxies
                    return
                else:
                    print(
                        f"{red('[MISMATCH]')} {magenta(result.proxy)} -> {result.title} (expected '{red(expected_title)}')"
                    )
                    db.update_data(proxy=result.proxy, data={"private": "true"})
            else:
                print(f"{red('[FAIL]')} {magenta(result.proxy)}")


if __name__ == "__main__":
    # Parse args and create file lock
    args = parse_args(default_limit=10)
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    try:
        proxy_file = get_relative_path("proxies.txt")
        db = init_db("mysql")
        marker = SQLiteMarker(
            db_filename="proxy_private_checker.sqlite",
            table_name="checked_proxies",
            key_column="proxy",
            base_dir="tmp/database",
        )

        try:
            # Use retrieve_proxies and provide a custom_filter that applies
            # the "last checked before today" check and marker filtering and
            # row "private" should not be "true" or "false" (i.e. not previously checked).
            def custom_filter(rows: List[dict[str, Any]]) -> List[dict[str, Any]]:
                # Include rows that either have no last_check (e.g. loaded from file)
                # or whose last_check is older than the configured threshold.
                rows = [
                    r
                    for r in rows
                    if isinstance(r, dict)
                    and (
                        not r.get("last_check")
                        or is_date_rfc3339_older_than(r.get("last_check"), hours=24)
                    )
                    and r.get("private") not in ("true", "false")
                ]

                proxy_by_key: dict[str, dict[str, Any]] = {}
                ordered_keys: List[str] = []
                for proxy in rows:
                    proxy_value = str(proxy.get("proxy") or "").strip()
                    if not proxy_value:
                        continue

                    marker_key = normalize_proxy_value(proxy_value)
                    if marker_key in proxy_by_key:
                        continue

                    proxy_by_key[marker_key] = proxy
                    ordered_keys.append(marker_key)

                pending_keys, already_checked = marker.filter_unseen(ordered_keys)
                return [proxy_by_key[key] for key in pending_keys]

            result: ProxyRetrievalResult = retrieve_proxies(
                db=db, limit=args.limit, custom_filter=custom_filter
            )
            proxies = result.proxies
            source_label = result.source_label

            random.shuffle(proxies)
            print(f"[INFO] Proxy source: {source_label} ({len(proxies)} candidates)")

            # Run the async checks
            asyncio.run(run_checks(proxies, args, db))

            # mark tested proxies
            tested_set = {
                normalize_proxy_value(proxy.get("proxy") or "") for proxy in proxies
            }
            for tested_proxy in tested_set:
                # Mark proxies as seen for 1 day to avoid permanent exclusion
                marker.mark(tested_proxy, valid_until=1)

            # If loaded from file, remove tested entries from it
            if (
                source_label.startswith("file://")
                and tested_set
                and os.path.isfile(proxy_file)
            ):
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
                        f"[INFO] Removed {removed_count} tested proxies from {proxy_file}; trimmed {trimmed_trailing_empty} trailing empty lines"
                    )
        finally:
            marker.close()
            db.close()
    finally:
        if locker:
            locker.unlock()
