from __future__ import annotations

import os
import sys
import time
from typing import cast

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from artisan.cleaner_func import fetch_md5_hash_dirs
from src.func import get_relative_path
from src.func_date import day_to_seconds
from src.shared import init_mysql_db, init_sqlite_db
from src.utils.parse_args import parse_args
from src.ProxyDB import ProxyDB


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


def _remove_file_if_expired(path: str, expire_seconds: int) -> None:
    now = time.time()
    try:
        if now - os.stat(path).st_mtime > expire_seconds:
            print(f"Removing old file: {path}")
            os.remove(path)
    except FileNotFoundError:
        pass
    except Exception as e:
        print(f"Error removing file {path}: {e}")


def _remove_file_if_too_large(path: str, max_kb: int) -> None:
    try:
        size_kb = os.path.getsize(path) / 1024
        if size_kb > max_kb:
            print(f"Removing oversized file: {path} ({size_kb:.2f} KB)")
            os.remove(path)
    except FileNotFoundError:
        pass
    except Exception as e:
        print(f"Error removing oversized file {path}: {e}")


def _remove_old_files(base_path: str, expire_seconds: int) -> None:
    for root, _, files in os.walk(base_path, topdown=False):
        for name in files:
            _remove_file_if_expired(os.path.join(root, name), expire_seconds)


def clean_directory(
    base_path: str,
    expire_seconds: int = 0,
) -> None:
    if expire_seconds <= 0:
        expire_seconds = day_to_seconds(7)

    if not os.path.exists(base_path):
        return

    _remove_old_files(base_path, expire_seconds)

    for root, dirs, _ in os.walk(base_path, topdown=False):
        for dirname in dirs:
            _remove_empty_directory(os.path.join(root, dirname))

    _remove_empty_directory(base_path)


def main() -> None:
    parse_args(description="Python Cleaner Script")

    mysql = init_mysql_db()
    sqlite = init_sqlite_db()

    for label, db in (
        ("MySQL", mysql),
        ("SQLite", sqlite),
    ):
        print(f"[{label}] Proxy counts by status:")
        if db:
            for item in db.count_by_status():
                print(
                    f"  {item.get('status') or '(empty)'}: " f"{item.get('count', 0)}"
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

    _remove_file_if_too_large(get_relative_path("error.txt"), 200)


if __name__ == "__main__":
    main()
