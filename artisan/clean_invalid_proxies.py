from __future__ import annotations

import hashlib
import os
from pathlib import Path
import sys
from typing import Any, Callable

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import extract_proxies, is_valid_proxy

from src.func_date import normalize_rfc3339
from src.func import get_relative_path
from src.func_console import green, red
from src.ProxyDB import ProxyDB
from src.database.SQLiteMarker import SQLiteMarker
from src.shared import init_db, init_sqlite_db


class FatalDBError(Exception):
    pass


def clone_database(db: ProxyDB) -> ProxyDB:
    return ProxyDB(
        db_location=db.db_location,
        start=True,
        db_type=db.driver,
        mysql_host=getattr(db, "mysql_host", "localhost"),
        mysql_dbname=getattr(db, "mysql_dbname", "php_proxy_hunter"),
        mysql_user=getattr(db, "mysql_user", "root"),
        mysql_password=getattr(db, "mysql_password", ""),
    )


def to_project_relative_path(path: Any) -> str | None:
    if not path or not isinstance(path, str):
        return path
    if isinstance(path, Path):
        path = str(path)

    project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
    return os.path.relpath(os.path.abspath(path), project_root)


def make_marker_key(proxy: str, driver: str, db_location: Any) -> str:
    location = ""
    if db_location is not None:
        location = str(db_location)
    payload = f"{proxy}|{driver}|{location}"
    return hashlib.md5(payload.encode("utf-8")).hexdigest()


def process_proxy(
    db_factory: Callable[[], ProxyDB],
    data: dict[str, Any],
    driver: str,
) -> str | None:
    db = db_factory()
    try:
        proxy = str(data.get("proxy", ""))
        normalized_proxy = db.normalize_proxy(proxy)
        marker_key = make_marker_key(proxy, driver, db.db_location)

        if proxy != normalized_proxy:
            print(
                f"[{driver}] Processing proxy: {red(proxy)} -> Normalized: {green(normalized_proxy)}"
            )
        else:
            print(f"[{driver}] Processing proxy: {green(proxy)}")
        # fix invalid DATE RFC3339 format in last_check if exists
        last_check = data.get("last_check")
        if last_check and isinstance(last_check, str):
            normalized_last_check = normalize_rfc3339(last_check)
            if normalized_last_check and normalized_last_check != last_check:
                data["last_check"] = normalized_last_check

            # Update database if last_check was modified
            if "last_check" in data and data["last_check"] != last_check:
                try:
                    db.update_data(proxy, data)
                except Exception as e:
                    print(
                        f"[{driver}] Failed to update last_check for proxy {proxy}: {e}"
                    )
                    raise FatalDBError(e)

        # when normalized empty, it means it's invalid and cannot be fixed by normalization, delete it
        if not normalized_proxy:
            print(f"[{driver}] Invalid proxy format, cannot normalize: {red(proxy)}")
            db.remove(proxy)
            return None

        valid_normalized = is_valid_proxy(normalized_proxy)
        valid_original = is_valid_proxy(proxy)

        # Completely invalid proxy
        if not valid_normalized and not valid_original:
            extract = extract_proxies(proxy)

            if extract:
                extracted_proxy = extract[0].proxy

                print(
                    f"[{driver}] Extracted valid proxy from invalid format: "
                    f"{red(proxy)} -> {green(extracted_proxy)}"
                )

                new_data = data.copy()
                new_data["proxy"] = extracted_proxy

                try:
                    db.update_data(extracted_proxy, new_data)
                except Exception as e:
                    print(f"[{driver}] Failed to update proxy in database: {e}")
                    raise FatalDBError(e)

                db.remove(proxy)
                return marker_key

            print(
                f"[{driver}] Invalid proxy: "
                f"{red(proxy)} -> Normalized: {red(normalized_proxy)}"
            )

            db.remove(proxy)
            return None

        # Needs normalization
        if proxy != normalized_proxy:
            print(
                f"[{driver}] Invalid proxy format: "
                f"{red(proxy)} -> Normalized: {green(normalized_proxy)}"
            )

            db.remove(proxy)

            new_data = data.copy()
            new_data["proxy"] = normalized_proxy

            try:
                db.update_data(normalized_proxy, new_data)
            except Exception as e:
                print(f"[{driver}] Failed to update proxy in database: {e}")
                raise FatalDBError(e)

        return marker_key
    finally:
        close_database(db)


def remover(db: ProxyDB):
    page = 1
    per_page = 1000

    driver = (
        f"{db.driver} "
        f"{f'({to_project_relative_path(db.db_location)})' if db.driver == 'sqlite' else ''}"
    ).strip()

    db_factory = lambda: clone_database(db)
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

            proxy_by_value: dict[str, dict[str, Any]] = {}
            ordered_marker_keys: list[str] = []
            for data in proxies:
                proxy_value = str(data.get("proxy", "")).strip()
                if not proxy_value or proxy_value in proxy_by_value:
                    continue
                proxy_by_value[proxy_value] = data
                ordered_marker_keys.append(
                    make_marker_key(proxy_value, driver, db.db_location)
                )

            pending_proxy_values, already_checked = marker.filter_unseen(
                ordered_marker_keys
            )
            if already_checked:
                print(
                    f"[{driver}] Skipping {already_checked} recently processed proxies"
                )

            for marker_key in pending_proxy_values:
                proxy_value = next(
                    key
                    for key in proxy_by_value
                    if make_marker_key(key, driver, db.db_location) == marker_key
                )
                data = proxy_by_value[proxy_value]
                marked_proxy = process_proxy(
                    db_factory=db_factory,
                    data=data,
                    driver=driver,
                )
                # Mark the proxy as valid for 7 days if it was processed and is valid after processing
                if marked_proxy and is_valid_proxy(proxy_value):
                    marker.mark(marked_proxy, valid_until=7)

            page += 1
    finally:
        marker.close()


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


def close_database(db: ProxyDB):
    try:
        db.close()
    except Exception as e:
        print(f"[{db.driver}] Error occurred while closing database: {e}")


if __name__ == "__main__":
    main()
