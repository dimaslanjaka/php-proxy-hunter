import asyncio
import sys
import os
from typing import Any, Dict, List, Optional, TypedDict

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from proxy_hunter import Proxy, dict_to_proxy_list, is_port_open
from src.utils.parse_args import parse_args
from src.database.SQLiteMarker import SQLiteMarker
from src.func import get_relative_path
from src.func_date import get_current_rfc3339_time, get_yesterday_rfc3339_time
from src.func_console import green, red
from src.shared import init_db, init_readonly_db
from src.utils.date.timeAgo import time_ago
from src.utils.file.FileLockHelper import FileLockHelper

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


class CheckResult(TypedDict):
    proxy: str
    status: str
    last_check_ago: str
    previous_status: Optional[str]
    updated: bool


class Stats(TypedDict):
    open: int
    closed: int
    updated: int
    errors: int
    already_checked: int


def _get_sql_placeholder(db: Any) -> str:
    return "%s" if getattr(db, "driver", "") == "mysql" else "?"


def _update_proxy_status(db: Any, proxy_str: str, status: str) -> None:
    db.get_db().update(
        "proxies",
        {"status": status, "last_check": get_current_rfc3339_time()},
        f"proxy = {_get_sql_placeholder(db)}",
        [proxy_str],
    )


async def check_proxy_port(
    proxy: Proxy, db: Any, semaphore: asyncio.Semaphore
) -> Optional[CheckResult]:
    """Check port status for a single proxy concurrently."""
    async with semaphore:
        proxy_str = proxy.proxy
        if not proxy_str:
            return None

        status = proxy.status
        if status == "port-open":
            return {
                "proxy": proxy_str,
                "status": "OPEN",
                "last_check_ago": time_ago(proxy.last_check),
                "previous_status": status,
                "updated": False,
            }

        loop = asyncio.get_running_loop()
        is_open = await loop.run_in_executor(None, is_port_open, proxy_str)

        last_check = proxy.last_check
        last_check_ago = time_ago(last_check)

        updated = False
        next_status = None

        if is_open:
            if status not in ("port-open", "untested", "active"):
                next_status = "port-open"
        elif status != "port-closed":
            next_status = "port-closed"

        if next_status:
            _update_proxy_status(db, proxy_str, next_status)
            updated = True

        return {
            "proxy": proxy_str,
            "status": "OPEN" if is_open else "CLOSED",
            "last_check_ago": last_check_ago,
            "previous_status": status,
            "updated": updated,
        }


async def process_proxies_async(
    proxies: List[Proxy], db: Any, concurrency: int
) -> Stats:
    """Process all proxies concurrently with concurrency limit."""
    semaphore = asyncio.Semaphore(max(1, concurrency))

    marker = SQLiteMarker(
        db_filename="filter_open_port.sqlite",
        table_name="checked_proxies",
        key_column="proxy",
        base_dir="tmp/database",
    )
    try:
        proxy_by_value: Dict[str, Proxy] = {}
        ordered_proxy_values: list[str] = []
        for proxy in proxies:
            proxy_str = str(proxy.proxy or "").strip()
            if not proxy_str or proxy_str in proxy_by_value:
                continue
            proxy_by_value[proxy_str] = proxy
            ordered_proxy_values.append(proxy_str)

        pending_proxy_values, already_checked = marker.filter_unseen(
            ordered_proxy_values
        )
        valid_proxies = [proxy_by_value[proxy] for proxy in pending_proxy_values]
        if not valid_proxies:
            return {
                "open": 0,
                "closed": 0,
                "updated": 0,
                "errors": 0,
                "already_checked": already_checked,
            }

        tasks = [
            asyncio.create_task(check_proxy_port(proxy, db, semaphore))
            for proxy in valid_proxies
        ]
        stats: Stats = {
            "open": 0,
            "closed": 0,
            "updated": 0,
            "errors": 0,
            "already_checked": already_checked,
        }

        # Log results as they complete, not after all finish
        for coro in asyncio.as_completed(tasks):
            try:
                result = await coro
            except Exception:
                stats["errors"] += 1
                continue

            if result is None:
                continue

            if result["status"] == "OPEN":
                stats["open"] += 1
                status_text = green(result["status"])
            else:
                stats["closed"] += 1
                status_text = red(result["status"])

            if result["updated"]:
                stats["updated"] += 1

            marker.mark(str(result["proxy"]))

            suffix = (
                f" ({result['previous_status']})" if result["previous_status"] else ""
            )
            print(
                f"{result['proxy']} {status_text} last checked {result['last_check_ago']}"
                f"{suffix}"
            )

        return stats
    finally:
        marker.close()


if __name__ == "__main__":
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
    if not locker.lock():
        print("Another instance is already running. Exiting.")
        sys.exit(0)

    db = None
    try:
        try:
            db = init_db("mysql")
            if getattr(db, "db", None) is None:
                raise RuntimeError("MySQL initialization failed")
        except Exception as exc:
            db = init_readonly_db()
            if getattr(db, "db", None) is None:
                raise RuntimeError("Readonly database initialization failed") from exc

        args = parse_args(default_limit=10, default_concurrency=4)
        yesterday_date_rfc3339 = get_yesterday_rfc3339_time()
        proxies = db.get_working_proxies(
            randomize=True,
            limit=args.limit,
            last_checked=yesterday_date_rfc3339,
        )

        print(
            f"Checking {len(proxies)} proxies concurrently ({args.concurrency} workers)"
        )

        typed_proxies: List[Proxy] = dict_to_proxy_list(proxies)
        stats = asyncio.run(process_proxies_async(typed_proxies, db, args.concurrency))

        print(
            f"Finished: open={stats['open']}, closed={stats['closed']}, "
            f"status_updates={stats['updated']}, errors={stats['errors']}, "
            f"already_checked={stats['already_checked']}"
        )
    finally:
        if db:
            db.close()
        if locker:
            locker.unlock()
