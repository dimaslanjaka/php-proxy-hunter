import os
import sys
import re
from typing import Any, Dict, Iterable, List, Optional, Tuple, Union

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.append(PROJECT_ROOT)

from proxy_hunter import extract_proxies
from src.ProxyDB import ProxyDB
from src.utils.parse_args import parse_args, ParseArgs
from src.func import get_relative_path
from dataclasses import dataclass
from typing import Any, Callable, Dict, Optional, Tuple


@dataclass
class ProxyRetrievalResult:
    proxies: List[Dict[str, Any]]
    source_label: str


def retrieve_proxies(
    db: ProxyDB,
    proxy_file_default: str = "proxies.txt",
    limit: Optional[int] = None,
    randomize: bool = True,
    custom_filter: Optional[
        Callable[[List[Dict[str, Any]]], List[Dict[str, Any]]]
    ] = None,
) -> ProxyRetrievalResult:
    """Retrieve proxies from CLI, a file, or the database.

    The function resolves CLI arguments by calling ``parse_args()`` internally.
    It attempts to load proxies in this order: CLI input, a proxy file, an
    untested set from the DB, then all DB proxies. If ``custom_filter`` is
    provided it is applied to the produced list of proxy rows before the
    result is returned.

    Args:
        db (ProxyDB): Database instance exposing the DB access methods used.
        proxy_file_default (str): Default file path to use when no CLI file is set.
        limit (Optional[int]): Optional limit passed to DB queries.
        randomize (bool): Whether to randomize DB results.
        custom_filter (Optional[Callable[[List[Dict[str, Any]]], List[Dict[str, Any]]]]):
            Optional callable to post-process/filter retrieved proxy rows.

    Returns:
        ProxyRetrievalResult: dataclass with ``proxies`` and ``source_label``.

    Notes:
        - ``source_label`` will be one of: "cli", "file://<path>", or "db".
        - The caller may provide a ``custom_filter`` to implement marker-based
          or date-based filtering (the checkers in this repo do this).
    """
    args = parse_args()
    proxy_file = str(
        getattr(args, "proxy_file", "") or get_relative_path(proxy_file_default)
    )
    proxies: List[Dict[str, Any]] = []
    source_label = "db"

    cli_rows = to_proxy_rows(load_proxies_from_cli())
    if len(cli_rows) != 0:
        proxies = cli_rows
        if getattr(args, "proxy_file", None) and args.proxy_file:
            proxy_file = (
                args.proxy_file if os.path.exists(args.proxy_file) else proxy_file
            )
            source_label = f"file://{proxy_file}"
        else:
            source_label = "cli"

    if not proxies:
        file_rows = to_proxy_rows(load_proxies_from_file(proxy_file))
        if file_rows:
            proxies = file_rows
            source_label = f"file://{proxy_file}"

    if not proxies:
        # prefer untested when possible
        # ensure we fetch a sufficiently large slice from the DB so
        # caller-side filtering (marker/date) still has candidates
        db_limit = max(limit or 0, 1000)
        rows = (
            db.get_untested_proxies(limit=db_limit, randomize=randomize)
            or db.get_working_proxies(limit=db_limit, randomize=randomize)
            or []
        )

        proxies = to_proxy_rows(rows)
        source_label = "db"

    if not proxies:
        proxies = to_proxy_rows(db.get_all_proxies(limit=limit, randomize=randomize))
        source_label = "db"

    # apply optional caller filter
    if custom_filter is not None:
        try:
            proxies = custom_filter(proxies)
        except Exception:
            pass

    return ProxyRetrievalResult(proxies=proxies, source_label=source_label)


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


def load_proxies_from_cli() -> List[Any]:
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
    proxies: List[object] = []
    invalid_rows = 0

    for item in parsed:
        proxy_str = str(getattr(item, "proxy", "") or "").strip()
        normalized = normalize_proxy_str(proxy_str)
        if not normalized:
            invalid_rows += 1
            continue
        # preserve the original parsed object (may contain username/password)
        proxies.append(item)

    print(f"Loaded {len(proxies)} proxies from CLI input")
    if invalid_rows:
        print(f"Skipped {invalid_rows} invalid proxy rows from CLI input")

    return proxies


def load_proxies_from_file(file_path: str) -> List[Any]:
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
    proxies: List[object] = []
    invalid_rows = 0

    for item in parsed:
        proxy_str = str(getattr(item, "proxy", "") or "").strip()
        normalized = normalize_proxy_str(proxy_str)
        if not normalized:
            invalid_rows += 1
            continue
        # preserve parsed object so username/password survive
        proxies.append(item)

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


def normalize_proxy_value(value: Union[str, Dict[str, Any]]) -> str:
    if isinstance(value, dict):
        if value.get("username") and value.get("password") and value.get("proxy"):
            value = f"{value['username']}:{value['password']}@{value['proxy']}"
        else:
            value = value.get("proxy", "")
    text = str(value or "").strip()
    # remove leading scheme such as socks5://, socks4://, http://, https://
    # or any single '<scheme>://' occurrence
    text = re.sub(
        r"^(?:socks5|socks4|https?|[^/:]+)://", "", text, count=1, flags=re.IGNORECASE
    )
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
        # Support Proxy objects (or any object with .proxy/.username/.password)
        if not isinstance(item, (str, dict, tuple, list)) and hasattr(item, "proxy"):
            proxy_value = str(getattr(item, "proxy") or "").strip()
            # include credentials if present
            username = getattr(item, "username", None)
            password = getattr(item, "password", None)
            if username:
                row["username"] = username
            if password:
                row["password"] = password

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
            # preserve username/password from dict if provided
            if item.get("username"):
                row["username"] = item.get("username")
            if item.get("password"):
                row["password"] = item.get("password")
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
