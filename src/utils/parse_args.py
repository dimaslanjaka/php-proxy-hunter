import argparse
from dataclasses import dataclass
from typing import Optional


def _str_to_bool(value: Optional[str]) -> bool:
    """Parse common truthy/falsey CLI values into a boolean.

    - If `--admin` is provided without a value, argparse will pass the `const` value
      which will be interpreted as true.
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
        default=None,
        help="DB proxy load limit when no CLI proxy input is provided (alias: --max)",
    )
    parser.add_argument(
        "--max",
        type=int,
        default=None,
        help="Alias for --limit; used when --limit is not provided",
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

    # Prefer explicit --limit; if not provided, fall back to --max; otherwise use default
    raw_limit = getattr(ns, "limit", None)
    raw_max = getattr(ns, "max", None)
    final_limit = (
        raw_limit
        if raw_limit is not None
        else (raw_max if raw_max is not None else default_limit)
    )

    return ParseArgs(
        proxy_string=getattr(ns, "proxy_string", None),
        single_proxy=getattr(ns, "single_proxy", None),
        proxy_file=getattr(ns, "proxy_file", None),
        limit=final_limit,
        concurrency=getattr(ns, "concurrency", default_concurrency),
        uid=getattr(ns, "uid", None),
        admin=getattr(ns, "admin", False),
    )
