from __future__ import annotations

import hashlib
import os
from pathlib import Path
import sys
from typing import Any

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import extract_proxies, is_valid_proxy
from src.func_date import normalize_rfc3339
from src.func import get_relative_path
from src.func_console import green, red
from src.ProxyDB import ProxyDB
from src.database.SQLiteMarker import SQLiteMarker
from src.shared import init_db, init_sqlite_db
from src.utils.file.FileLockHelper import FileLockHelper

current_filename = os.path.basename(__file__)
locker: FileLockHelper | None = None


def to_project_relative_path(path: Any) -> str | None:
    if not path:
        return path

    if isinstance(path, Path):
        path = str(path)

    if not isinstance(path, str):
        return None

    project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

    return os.path.relpath(os.path.abspath(path), project_root)


def make_marker_key(proxy: str, driver: str, db_location: Any) -> str:
    location = ""

    if db_location is not None:
        location = str(db_location)

    payload = f"{proxy}|{driver}|{location}"

    return hashlib.md5(payload.encode("utf-8")).hexdigest()


def configure_sqlite(db: ProxyDB):
    if db.driver != "sqlite":
        return

    helper = db.get_db()

    try:
        helper.execute_query("PRAGMA journal_mode=WAL")
        helper.execute_query("PRAGMA synchronous=NORMAL")
        helper.execute_query("PRAGMA temp_store=MEMORY")
        helper.execute_query("PRAGMA cache_size=-20000")
        helper.execute_query("PRAGMA busy_timeout=30000")
    except Exception as e:
        print(f"[sqlite] PRAGMA error: {e}")


def log_db_error(driver: str, action: str, error: Exception):
    print(f"[{driver}] {action} failed: {error}")


def safe_update_data(db: ProxyDB, proxy: str, data: dict[str, Any], driver: str):
    try:
        payload = data.copy()
        payload.pop("id", None)
        db.update_data(proxy, payload)
    except Exception as e:
        log_db_error(driver, f"update {proxy}", e)


def safe_remove(db: ProxyDB, proxy: str, driver: str):
    try:
        db.remove(proxy)
    except Exception as e:
        log_db_error(driver, f"remove {proxy}", e)


def process_proxy(db: ProxyDB, data: dict[str, Any], driver: str) -> str | None:
    proxy = str(data.get("proxy", "")).strip()

    normalized_proxy = db.normalize_proxy(proxy)

    marker_key = make_marker_key(proxy, driver, db.db_location)

    last_check = data.get("last_check")

    if last_check and isinstance(last_check, str):
        fixed = normalize_rfc3339(last_check)

        if fixed and fixed != last_check:
            data["last_check"] = fixed
            safe_update_data(db, proxy, data, driver)

    if not normalized_proxy:
        print(f"[{driver}] invalid proxy: {red(proxy)} -> {red(normalized_proxy)}")
        safe_remove(db, proxy, driver)
        return None

    valid_n = is_valid_proxy(normalized_proxy)
    valid_o = is_valid_proxy(proxy)

    if not valid_n and not valid_o:
        extracted = extract_proxies(proxy)

        if extracted:
            extracted_proxy = extracted[0].proxy

            print(f"[{driver}] extracted: {red(proxy)} -> {green(extracted_proxy)}")

            new_data = data.copy()
            new_data["proxy"] = extracted_proxy

            safe_update_data(db, extracted_proxy, new_data, driver)

            safe_remove(db, proxy, driver)
            return marker_key

        print(f"[{driver}] invalid: {red(proxy)} -> {red(normalized_proxy)}")
        safe_remove(db, proxy, driver)
        return None

    if proxy != normalized_proxy:
        print(f"[{driver}] normalized: {red(proxy)} -> {green(normalized_proxy)}")

        safe_remove(db, proxy, driver)

        new_data = data.copy()
        new_data["proxy"] = normalized_proxy

        safe_update_data(db, normalized_proxy, new_data, driver)

    return marker_key


def remover(db: ProxyDB):
    configure_sqlite(db)

    page = 1
    per_page = 1000

    driver = f"{db.driver} {f'({to_project_relative_path(db.db_location)})' if db.driver == 'sqlite' else ''}".strip()

    marker = SQLiteMarker(
        db_filename="clean_invalid_proxies.sqlite",
        table_name="cleaned_proxies",
        key_column="proxy",
        base_dir="tmp/database",
    )

    try:
        while True:
            proxies = db.get_all_proxies(page=page, per_page=per_page)

            if not proxies:
                break

            proxy_by_marker: dict[str, dict[str, Any]] = {}
            ordered: list[str] = []

            for data in proxies:
                p = str(data.get("proxy", "")).strip()

                if not p:
                    continue

                k = make_marker_key(p, driver, db.db_location)

                proxy_by_marker[k] = data
                ordered.append(k)

            pending, skipped = marker.filter_unseen(ordered)

            if skipped:
                print(f"[{driver}] skipped {skipped}")

            for k in pending:
                data = proxy_by_marker.get(k)

                if not data:
                    continue

                proxy = str(data.get("proxy", "")).strip()

                result = process_proxy(db, data, driver)

                if result and is_valid_proxy(proxy):
                    marker.mark(result, valid_until=7)

            page += 1

    finally:
        marker.close()


def close_database(db: ProxyDB):
    try:
        db.close()
    except Exception as e:
        print(f"[{db.driver}] close error: {e}")


def main():
    databases = [
        init_db(),
        init_sqlite_db(get_relative_path("src/database.sqlite")),
        init_sqlite_db(get_relative_path("tmp/database.sqlite")),
    ]

    try:
        for db in databases:
            remover(db)
    finally:
        for db in databases:
            close_database(db)


if __name__ == "__main__":
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))

    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    try:
        main()
    finally:
        if locker:
            locker.unlock()
