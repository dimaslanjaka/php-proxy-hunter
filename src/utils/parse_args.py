import argparse
from dataclasses import dataclass
from typing import Optional, Any, TypeVar

# Generic type for attr() return value inference
T = TypeVar("T")


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
    single: bool = False
    file_lock: Optional[str] = None
    admin: bool = False

    def attr(self, name: str, default: T) -> T:
        """Return attribute value if present, otherwise `default`.

        `default` is required and its type determines the generic return type `T`.
        """
        return getattr(self, name, default)


def parse_args(
    default_limit: int = 100,
    default_concurrency: int = 4,
    description: str = "Find a tun2socks-compatible SOCKS5 proxy",
    additional: Optional[list] = None,
) -> ParseArgs:
    """Parse common CLI arguments used across artisan scripts.

    Parameters
    - default_limit: fallback value used when neither `--limit` nor `--max` are provided.
    - default_concurrency: default number of workers when `--concurrency` is not provided.
    - description: text passed to `argparse.ArgumentParser(description=...)`.
    - additional: optional list of dicts to dynamically register extra arguments.
      Each dict may contain keys like `flag`/`flags` (string or list), `dest`,
      `description` (maps to argparse `help`), `action` (argparse action string,
      e.g. 'store_true'), `type`, and `default`.

    Returns:
    - `ParseArgs` dataclass with parsed values. Any dynamically added args are
      attached as attributes on the returned instance so callers can access
      `args.<name>`.
    """
    parser = argparse.ArgumentParser(description=description)
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
        "--fileLock",
        dest="file_lock",
        type=str,
        help="Path to a lock file to prevent concurrent runs (alias: --file-lock)",
    )
    parser.add_argument(
        "-s",
        "--single",
        dest="single",
        action="store_true",
        help="Process a single item/run (shorthand -s)",
    )
    parser.add_argument(
        "--admin",
        nargs="?",
        const="true",
        default=False,
        type=_str_to_bool,
        help="Admin mode. Use --admin, --admin=true or --admin=false.",
    )

    # Register any additional dynamic args
    if additional:
        for spec in additional:
            if not isinstance(spec, dict):
                continue
            flags = None
            if "flags" in spec:
                flags = spec.get("flags")
            elif "flag" in spec:
                flags = spec.get("flag")
            elif "action" in spec:
                # allow passing a flag via the 'action' key for convenience
                action_val = spec.get("action")
                if isinstance(action_val, str) and action_val.startswith("-"):
                    flags = action_val

            if not flags:
                continue
            if isinstance(flags, str):
                flags = [flags]

            kwargs = {}
            # help/description
            if "help" in spec:
                kwargs["help"] = spec.get("help")
            elif "description" in spec:
                kwargs["help"] = spec.get("description")
            # dest
            if "dest" in spec:
                kwargs["dest"] = spec.get("dest")
            # argparse action (store_true, store_false, etc.)
            if "action_type" in spec:
                kwargs["action"] = spec.get("action_type")
            elif "action" in spec and spec.get("action") in (
                "store_true",
                "store_false",
                "store_const",
                "append",
                "count",
            ):
                kwargs["action"] = spec.get("action")
            # type handling: accept type object or short string
            if "type" in spec:
                t = spec.get("type")
                if isinstance(t, str):
                    if t == "int":
                        kwargs["type"] = int
                    elif t == "float":
                        kwargs["type"] = float
                    elif t == "bool":
                        kwargs["type"] = _str_to_bool
                    else:
                        # fallback: do not set
                        pass
                else:
                    kwargs["type"] = t
            if "default" in spec:
                kwargs["default"] = spec.get("default")

            try:
                parser.add_argument(*flags, **kwargs)
            except Exception:
                # ignore malformed spec to preserve robustness
                continue
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

    # Determine single mode: explicit flag or when explicit --limit/--max equals 1
    explicit_single = getattr(ns, "single", False)
    single_flag = explicit_single or (raw_limit == 1 or raw_max == 1)

    result = ParseArgs(
        proxy_string=getattr(ns, "proxy_string", None),
        single_proxy=getattr(ns, "single_proxy", None),
        proxy_file=getattr(ns, "proxy_file", None),
        limit=final_limit,
        concurrency=getattr(ns, "concurrency", default_concurrency),
        uid=getattr(ns, "uid", None),
        single=single_flag,
        file_lock=getattr(ns, "file_lock", None),
        admin=getattr(ns, "admin", False),
    )

    # Attach any dynamic args from namespace to the returned dataclass instance
    for name, val in vars(ns).items():
        if not hasattr(result, name):
            try:
                setattr(result, name, val)
            except Exception:
                pass

    return result
