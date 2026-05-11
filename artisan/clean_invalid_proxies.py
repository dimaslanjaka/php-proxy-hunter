from __future__ import annotations

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


def process_proxy(
    db_factory: Callable[[], ProxyDB],
    data: dict[str, Any],
    driver: str,
):
    db = db_factory()
    try:
        proxy = str(data.get("proxy", ""))
        normalized_proxy = db.normalize_proxy(proxy)

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
            return

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
                return

            print(
                f"[{driver}] Invalid proxy: "
                f"{red(proxy)} -> Normalized: {red(normalized_proxy)}"
            )

            db.remove(proxy)
            return

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

    while True:
        proxies = db.get_all_proxies(page=page, per_page=per_page)

        if not proxies:
            break

        for data in proxies:
            process_proxy(
                db_factory=db_factory,
                data=data,
                driver=driver,
            )

        page += 1


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
