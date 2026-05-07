import os
import time
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed

from proxy_hunter import is_valid_proxy

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_date import day_to_seconds
from src.ProxyDB import ProxyDB
from src.func import get_relative_path
from src.shared import init_db, init_readonly_db
from src.utils.parse_args import parse_args

# ---- CONFIG ----
MAX_WORKERS = 20


def clean_proxies(db: "ProxyDB"):
    """Remove invalid proxies using concurrency."""
    proxies = [p["proxy"] for p in db.get_all_proxies()]
    invalid = []

    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        future_map = {executor.submit(is_valid_proxy, p): p for p in proxies}

        for future in as_completed(future_map):
            proxy = future_map[future]
            try:
                if not future.result():
                    invalid.append(proxy)
            except Exception:
                invalid.append(proxy)

    for proxy in invalid:
        print(f"Deleting invalid proxy: {proxy}")
        try:
            if getattr(db, "driver", "").lower() == "mysql":
                sql = "DELETE FROM proxies WHERE proxy = %s"
            else:
                sql = "DELETE FROM proxies WHERE proxy = ?"
            db.get_db().execute_query(sql, [proxy.strip()])
        except Exception as e:
            print(f"Failed to delete proxy {proxy}: {e}")


def clean_directory(base_path: str, expire_seconds: int = 0) -> None:
    """Clean files older than `expire_seconds` and remove empty dirs.

    Args:
        base_path: Path to directory to clean.
        expire_seconds: Age threshold in seconds; files older than this are removed.
    """
    now = time.time()
    if expire_seconds == 0:
        expire_seconds = day_to_seconds(7)  # Default to 7 days

    if not os.path.exists(base_path):
        return

    for entry in os.scandir(base_path):
        path = entry.path
        try:
            if entry.is_dir():
                clean_directory(path, expire_seconds)
                if not os.listdir(path):
                    print(f"Removing empty directory: {path}")
                    os.rmdir(path)

            elif entry.is_file():
                if now - entry.stat().st_mtime > expire_seconds:
                    print(f"Removing old file: {path}")
                    os.remove(path)

        except Exception as e:
            print(f"Error cleaning {path}: {e}")

    # Remove base dir if empty
    if not os.listdir(base_path):
        print(f"Removing empty directory: {base_path}")
        os.rmdir(base_path)


if __name__ == "__main__":
    args = parse_args(
        description="Python Cleaner Script",
        additional=[
            {
                "flags": ["--readonly"],
                "description": "Use read-only DB connection (no updates)",
                "action": "store_true",
            }
        ],
    )

    db = init_readonly_db() if args.attr("readonly", False) else init_db("mysql")

    # ---- Show proxy stats ----
    try:
        counts = db.count_by_status()
        print("Proxy counts by status:")
        for item in counts:
            print(f"  {item.get('status') or '(empty)'}: {item.get('count', 0)}")
    except Exception as e:
        print("Failed to get proxy counts:", e)

    # ---- Clean proxies ----
    if not args.attr("readonly", False):
        clean_proxies(db)

    # ---- Clean directories ----
    dirs = [
        "tmp/logs/crontab",
        "tmp/build",
        "tmp/requests_cache",
        "tmp/caches",
        "tmp/runners",
        "tmp/sms",
        "tmp/mirror",
        "tmp/status",
        "tmp/download",
        "tmp/ips-ports",
    ]

    for d in dirs:
        clean_directory(get_relative_path(d))

    clean_directory(get_relative_path("tmp/proxies"), day_to_seconds(1))
