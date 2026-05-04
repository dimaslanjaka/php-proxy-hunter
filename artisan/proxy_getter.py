import os
import sys
from typing import Any, Dict, Iterable, List, Optional, Tuple

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.append(PROJECT_ROOT)

from proxy_hunter import extract_proxies
from src.utils.parse_args import parse_args, ParseArgs


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


def normalize_proxy_value(value: str) -> str:
    text = str(value or "").strip()
    if text.startswith("socks5://"):
        return text.replace("socks5://", "", 1)
    if "://" in text:
        return text.split("://", 1)[1]
    return text


def to_proxy_rows(items: Iterable[Any]) -> List[Dict[str, Any]]:
    """Map raw proxy inputs into lightweight rows.

    Accepts strings, dicts, and (host, port) tuples/lists and returns a list
    of dicts with at least the `proxy` key containing the normalized proxy
    value (no scheme).
    """
    rows: List[Dict[str, Any]] = []

    for item in items:
        proxy_value: Optional[str] = None
        row: Dict[str, Any] = {}

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
