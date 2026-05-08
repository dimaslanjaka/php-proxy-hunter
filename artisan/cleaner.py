import os
import time
import asyncio
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import List

from proxy_hunter import is_valid_proxy

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_date import day_to_seconds
from src.ProxyDB import ProxyDB
from src.func import get_relative_path
from src.shared import init_db, init_readonly_db
from src.utils.parse_args import parse_args
from artisan.cleaner_func import fetch_md5_hash_dirs

# ---- CONFIG ----
MAX_WORKERS = 20
BATCH_DELETE_SIZE = 500


def _remove_empty_directory(path: str) -> bool:
    if not os.path.isdir(path):
        return False

    try:
        if not os.listdir(path):
            print(f"Removing empty directory: {path}")
            os.rmdir(path)
            return True
    except FileNotFoundError:
        return False

    return False


async def clean_proxies(db: "ProxyDB"):
    """Async wrapper: run blocking `is_valid_proxy` in threads and collect invalid proxies.

    Uses `asyncio.to_thread` to avoid changing `proxy_hunter` internals.
    """
    # Stream proxies into a list of values to check. Minimizes memory spikes
    all_rows = db.get_all_proxies()
    if not all_rows:
        return

    proxies = [p["proxy"] for p in all_rows if p and p.get("proxy")]
    if not proxies:
        return

    worker_count = min(MAX_WORKERS, max(1, len(proxies)))
    semaphore = asyncio.Semaphore(worker_count)

    async def _check(proxy: str):
        async with semaphore:
            try:
                ok = await asyncio.to_thread(is_valid_proxy, proxy)
            except Exception:
                ok = False
            return proxy if not ok else None

    tasks = [asyncio.create_task(_check(str(p))) for p in proxies]
    results = await asyncio.gather(*tasks)
    invalid = [r for r in results if r]

    if not invalid:
        return

    # Delete invalid proxies in chunks to avoid very large parameter lists
    placeholder = "%s" if getattr(db, "driver", "").lower() == "mysql" else "?"
    for i in range(0, len(invalid), BATCH_DELETE_SIZE):
        chunk = invalid[i : i + BATCH_DELETE_SIZE]
        print(f"Deleting {len(chunk)} invalid proxies (chunk starting at index {i})...")
        try:
            placeholders = ", ".join([placeholder] * len(chunk))
            sql = f"DELETE FROM proxies WHERE proxy IN ({placeholders})"
            db.get_db().execute_query(sql, [p.strip() for p in chunk])
            for p in chunk:
                print(f"Deleted invalid proxy: {p}")
        except Exception as e:
            print(f"Failed to delete proxies chunk starting at {i}: {e}")


def clean_directory(base_path: str, expire_seconds: int = 0) -> None:
    """Clean files older than `expire_seconds` and remove empty dirs.

    Uses a bottom-up `os.walk` to minimize stat calls and simplify removal.
    """
    now = time.time()
    if expire_seconds == 0:
        expire_seconds = day_to_seconds(7)  # Default to 7 days

    if not os.path.exists(base_path):
        return

    # Walk bottom-up so files are removed before directories are considered
    for root, dirs, files in os.walk(base_path, topdown=False):
        # Remove old files
        for name in files:
            path = os.path.join(root, name)
            try:
                st = os.stat(path)
            except FileNotFoundError:
                continue
            except Exception as e:
                print(f"Error stat'ing {path}: {e}")
                continue

            try:
                if now - st.st_mtime > expire_seconds:
                    print(f"Removing old file: {path}")
                    os.remove(path)
            except Exception as e:
                print(f"Error removing file {path}: {e}")

        # Attempt to remove empty directories
        for d in dirs:
            dpath = os.path.join(root, d)
            _remove_empty_directory(dpath)

    # Finally attempt to remove the base directory itself if empty
    _remove_empty_directory(base_path)


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
        asyncio.run(clean_proxies(db))

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
        "tmp/database",
    ]

    for d in dirs:
        clean_directory(get_relative_path(d))

    clean_directory(get_relative_path("tmp/proxies"), day_to_seconds(1))
    for user_log_dir in fetch_md5_hash_dirs(get_relative_path("tmp/logs")):
        clean_directory(user_log_dir, day_to_seconds(1))
