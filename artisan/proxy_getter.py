import argparse
import os
import sys
from dataclasses import dataclass
from typing import List, Optional, Tuple

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.append(PROJECT_ROOT)

from proxy_hunter import extract_proxies


def _str_to_bool(value: Optional[str]) -> bool:
    """Parse common truthy/falsey CLI values into a boolean.

    - If `--admin` is provided without a value, argparse will pass the `const` value
      (see usage below) which will be interpreted as true.
    - Accepts '1','0','true','false','yes','no', etc.
    """
    if value is None:
        return False
    if isinstance(value, bool):
        return value
    val = str(value).strip().lower()
    if val in ("1", "true", "t", "yes", "y", "on"):
        return True
    if val in ("0", "false", "f", "no", "n", "off"):
        return False
    raise argparse.ArgumentTypeError(f"Invalid boolean value: {value}")


@dataclass
class ParseArgs:
    proxy_string: Optional[str] = None
    single_proxy: Optional[str] = None
    proxy_file: Optional[str] = None
    limit: int = 100
    concurrency: int = 4
    uid: Optional[str] = None
    admin: bool = False


def parse_args(
    default_limit: int = 100,
    default_concurrency: int = 4,
) -> ParseArgs:
    parser = argparse.ArgumentParser(
        description="Find a tun2socks-compatible SOCKS5 proxy"
    )
    parser.add_argument(
        "--str",
        dest="proxy_string",
        help="String content containing one or more proxies",
    )
    parser.add_argument(
        "--proxy",
        dest="single_proxy",
        help="Single proxy string (example: 127.0.0.1:1080)",
    )
    parser.add_argument(
        "--file",
        dest="proxy_file",
        help="Path to a file containing proxies (one per line)",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=default_limit,
        help="DB proxy load limit when no CLI proxy input is provided",
    )
    parser.add_argument(
        "--concurrency",
        type=int,
        default=default_concurrency,
        help="Number of workers to run in parallel",
    )
    parser.add_argument(
        "--uid",
        type=str,
        help="Override lock filename (unique id)",
    )
    parser.add_argument(
        "--admin",
        nargs="?",
        const="true",
        default=False,
        type=_str_to_bool,
        help="Admin mode. Use --admin, --admin=true or --admin=false.",
    )
    # Allow unknown args so callers can pass extra flags without failing
    # (useful when scripts are invoked with framework/container args).
    ns = parser.parse_known_args()[0]

    return ParseArgs(
        proxy_string=getattr(ns, "proxy_string", None),
        single_proxy=getattr(ns, "single_proxy", None),
        proxy_file=getattr(ns, "proxy_file", None),
        limit=getattr(ns, "limit", default_limit),
        concurrency=getattr(ns, "concurrency", default_concurrency),
        uid=getattr(ns, "uid", None),
        admin=getattr(ns, "admin", False),
    )


def normalize_proxy_str(proxy_str: str) -> Optional[Tuple[str, int]]:
    if ":" not in proxy_str:
        return None

    host, port_str = proxy_str.rsplit(":", 1)
    host = host.strip().strip("[]")

    try:
        port = int(port_str)
    except (TypeError, ValueError):
        return None

    if not host or not (1 <= port <= 65535):
        return None

    return host, port


def load_proxies_from_cli() -> List[Tuple[str, int]]:
    args = parse_args()
    # If a proxy file was provided, prefer loading from file.
    proxy_file = str(getattr(args, "proxy_file", "") or "").strip()
    if proxy_file:
        return load_proxies_from_file(proxy_file)

    raw_proxy_text = "\n".join(
        value
        for value in [
            str(getattr(args, "proxy_string", "") or "").strip(),
            str(getattr(args, "single_proxy", "") or "").strip(),
        ]
        if value
    )

    if not raw_proxy_text:
        return []

    parsed = extract_proxies(raw_proxy_text)
    proxies: List[Tuple[str, int]] = []
    invalid_rows = 0

    for item in parsed:
        proxy_str = str(getattr(item, "proxy", "") or "").strip()
        normalized = normalize_proxy_str(proxy_str)
        if not normalized:
            invalid_rows += 1
            continue
        proxies.append(normalized)

    print(f"Loaded {len(proxies)} proxies from CLI input")
    if invalid_rows:
        print(f"Skipped {invalid_rows} invalid proxy rows from CLI input")

    return proxies


def load_proxies_from_file(file_path: str) -> List[Tuple[str, int]]:
    """Load proxies from a text file and normalize them into (host, port)."""
    path = str(file_path or "").strip()
    if not path:
        print("Proxy file path is empty")
        return []

    if not os.path.isfile(path):
        print(f"Proxy file not found: {path}")
        return []

    try:
        with open(path, "r", encoding="utf-8") as f:
            raw_proxy_text = f.read()
    except OSError as exc:
        print(f"Unable to read proxy file {path}: {exc}")
        return []

    if not raw_proxy_text.strip():
        print(f"Proxy file is empty: {path}")
        return []

    parsed = extract_proxies(raw_proxy_text)
    proxies: List[Tuple[str, int]] = []
    invalid_rows = 0

    for item in parsed:
        proxy_str = str(getattr(item, "proxy", "") or "").strip()
        normalized = normalize_proxy_str(proxy_str)
        if not normalized:
            invalid_rows += 1
            continue
        proxies.append(normalized)

    print(f"Loaded {len(proxies)} proxies from file: {path}")
    if invalid_rows:
        print(f"Skipped {invalid_rows} invalid proxy rows from file")

    return proxies


def load_working_proxies_from_db(
    db,
    limit: Optional[int] = None,
    randomize: bool = True,
    skip_socks_filter: bool = False,
) -> List[Tuple[str, int]]:
    """Load proxies from DB and normalize them into (host, port)."""
    rows = db.get_working_proxies(limit=limit, randomize=randomize)
    proxies: List[Tuple[str, int]] = []
    invalid_rows = 0

    for row in rows:
        proxy_str = str(row.get("proxy") or "").strip()
        proxy_type = str(row.get("type") or "").lower()

        if not skip_socks_filter and proxy_type and "socks5" not in proxy_type:
            continue

        normalized = normalize_proxy_str(proxy_str)
        if not normalized:
            invalid_rows += 1
            continue

        proxies.append(normalized)

    mode = "all proxy types" if skip_socks_filter else "SOCKS5-filtered"
    print(f"Loaded {len(proxies)} working proxies from DB ({mode})")
    if invalid_rows:
        print(f"Skipped {invalid_rows} invalid proxy rows")

    return proxies
