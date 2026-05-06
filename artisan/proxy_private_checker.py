import asyncio
import os
import random
import sys
from typing import Any, Dict, List, Optional, cast

from proxy_hunter import build_request

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from dataclasses import dataclass

from bs4 import BeautifulSoup

from artisan.proxy_getter import (
    ProxyRetrievalResult,
    normalize_proxy_value,
    retrieve_proxies,
)
from src.func import get_relative_path
from src.func_console import cyan, magenta, red, yellow
from src.func_date import is_date_rfc3339_older_than
from src.ProxyDB import ProxyDB
from src.shared import init_db
from src.utils.file.FileLockHelper import FileLockHelper
from src.utils.file import remove_string_from_file
from src.utils.parse_args import ParseArgs, parse_args


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
                timeout=5,
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
) -> List[str]:
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
    # Collect the set of proxies we actually attempted to test and return it.
    tested_keys: set[str] = set()
    for p in proxies[: args.limit]:
        if not p.get("proxy"):
            continue

        proxy_value: str = str(p.get("proxy") or "").strip()
        if not proxy_value:
            continue

        # record normalized key for marker/cleanup
        try:
            tested_keys.add(normalize_proxy_value(proxy_value))
        except Exception:
            tested_keys.add(proxy_value)

        proxy: str = cast(str, proxy_value)
        if p.get("username") and p.get("password"):
            proxy = f"{p['username']}:{p['password']}@{proxy}"
        print(f"Testing proxy: {magenta(proxy)}")

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
                    # If this proxy succeeded and the original row contains
                    # credentials, treat it as a private proxy. Otherwise
                    # mark as not-private.
                    proxy_key = p.get("proxy") or result.proxy
                    if p.get("username") and p.get("password"):
                        db.update_data(
                            proxy=proxy_key,
                            data={
                                "private": "true",
                                "username": p["username"],
                                "password": p["password"],
                            },
                            update_time=True,
                        )
                    else:
                        db.update_data(proxy=proxy_key, data={"private": "false"})
                    # Found a working proxy for at least one config -> stop checking proxies
                    return list(tested_keys)
                else:
                    print(
                        f"{red('[MISMATCH]')} {magenta(result.proxy)} -> {result.title} (expected '{red(expected_title)}')"
                    )
                    db.update_data(proxy=result.proxy, data={"private": "true"})
            else:
                print(f"{red('[FAIL]')} {magenta(result.proxy)}")

    # After checking all configured endpoints for all proxies, return tested set
    return list(tested_keys)


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

        try:
            # Use retrieve_proxies and provide a custom_filter that applies
            # the "last checked before today" check and dedupes candidates.
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

                # Marker functionality removed; return all deduped candidates
                return [proxy_by_key[key] for key in ordered_keys]

            result: ProxyRetrievalResult = retrieve_proxies(
                db=db, limit=args.limit, custom_filter=custom_filter
            )
            proxies = result.proxies
            source_label = result.source_label
            source_file = getattr(result, "source_file", None)

            random.shuffle(proxies)
            print(f"[INFO] Proxy source: {source_label} ({len(proxies)} candidates)")

            # Run the async checks and get actually tested proxies
            tested_keys = set(asyncio.run(run_checks(proxies, args, db)))

            # Marker functionality removed; no persistent marking performed

            # If loaded from file, remove tested entries from it. Prefer
            # the explicit `source_file` returned by `retrieve_proxies`.
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
                        print(f"[WARN] Failed removing keys from file: {e}")

                    print(
                        f"[INFO] Attempted removal of tested proxies from {target_file}"
                    )
        finally:
            db.close()
    finally:
        if locker:
            locker.unlock()
