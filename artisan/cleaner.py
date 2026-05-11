from __future__ import annotations

import asyncio
import os
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from typing import TYPE_CHECKING

from proxy_hunter import is_valid_proxy

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from artisan.cleaner_func import fetch_md5_hash_dirs
from src.func import get_relative_path
from src.func_date import day_to_seconds
from src.shared import init_db, init_mysql_db, init_sqlite_db
from src.utils.parse_args import parse_args

if TYPE_CHECKING:
    from src.ProxyDB import ProxyDB

MAX_WORKERS = 20

BATCH_DELETE_SIZE = 500

EXECUTOR = ThreadPoolExecutor(max_workers=MAX_WORKERS)


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


def _validate_proxy(proxy: str) -> tuple[str, bool]:
    try:
        return proxy, bool(is_valid_proxy(proxy))

    except Exception:
        return proxy, False


async def _run_proxy_check(proxy: str) -> tuple[str, bool]:
    loop = asyncio.get_running_loop()

    return await loop.run_in_executor(
        EXECUTOR,
        _validate_proxy,
        proxy,
    )


async def delete_invalid_proxies(
    db: "ProxyDB",
    invalid: list[str],
) -> None:
    if not invalid:
        return

    placeholder = "%s" if getattr(db, "driver", "").lower() == "mysql" else "?"

    execute_query = db.get_db().execute_query

    for i in range(0, len(invalid), BATCH_DELETE_SIZE):
        chunk = invalid[i : i + BATCH_DELETE_SIZE]

        print(
            f"Deleting {len(chunk)} invalid proxies "
            f"(chunk starting at index {i})..."
        )

        try:
            placeholders = ", ".join([placeholder] * len(chunk))

            sql = f"DELETE FROM proxies " f"WHERE proxy IN ({placeholders})"

            execute_query(
                sql,
                [proxy.strip() for proxy in chunk],
            )

            for proxy in chunk:
                print(f"Deleted invalid proxy: {proxy}")

        except Exception as e:
            print(f"Failed to delete proxies " f"chunk starting at {i}: {e}")


async def clean_proxies(db: "ProxyDB") -> None:
    rows = db.get_all_proxies()

    if not rows:
        return

    proxies = [proxy for item in rows if (proxy := item.get("proxy"))]

    if not proxies:
        return

    invalid: list[str] = []

    pending: set[asyncio.Task] = set()

    for proxy in proxies:
        pending.add(asyncio.create_task(_run_proxy_check(str(proxy))))

        if len(pending) >= MAX_WORKERS:
            done, pending = await asyncio.wait(
                pending,
                return_when=asyncio.FIRST_COMPLETED,
            )

            for task in done:
                proxy_value, valid = task.result()

                if not valid:
                    invalid.append(proxy_value)

    for task in asyncio.as_completed(pending):
        proxy_value, valid = await task

        if not valid:
            invalid.append(proxy_value)

    await delete_invalid_proxies(db, invalid)


def clean_directory(
    base_path: str,
    expire_seconds: int = 0,
) -> None:
    now = time.time()

    if expire_seconds <= 0:
        expire_seconds = day_to_seconds(7)

    if not os.path.exists(base_path):
        return

    for root, dirs, files in os.walk(
        base_path,
        topdown=False,
    ):
        for name in files:
            path = os.path.join(root, name)

            try:
                if now - os.stat(path).st_mtime > expire_seconds:
                    print(f"Removing old file: {path}")

                    os.remove(path)

            except FileNotFoundError:
                continue

            except Exception as e:
                print(f"Error removing file {path}: {e}")

        for dirname in dirs:
            _remove_empty_directory(os.path.join(root, dirname))

    _remove_empty_directory(base_path)


async def main() -> None:
    parse_args(description="Python Cleaner Script")

    mysql = init_mysql_db()
    sqlite = init_sqlite_db()

    for label, db in (
        ("MySQL", mysql),
        ("SQLite", sqlite),
    ):
        print(f"[{label}] Proxy counts by status:")

        for item in db.count_by_status():
            print(f"  {item.get('status') or '(empty)'}: " f"{item.get('count', 0)}")

    await asyncio.gather(
        clean_proxies(mysql),
        clean_proxies(sqlite),
    )

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

    for directory in dirs:
        clean_directory(get_relative_path(directory))

    clean_directory(
        get_relative_path("tmp/proxies"),
        day_to_seconds(1),
    )

    for user_log_dir in fetch_md5_hash_dirs(get_relative_path("tmp/logs")):
        clean_directory(
            user_log_dir,
            day_to_seconds(1),
        )


if __name__ == "__main__":
    try:
        asyncio.run(main())

    finally:
        EXECUTOR.shutdown(wait=True)
