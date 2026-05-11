from __future__ import annotations

import asyncio
import os
from pathlib import Path
import sys
from typing import Any

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import extract_proxies, is_valid_proxy

from src.func import get_relative_path
from src.func_console import green, red
from src.ProxyDB import ProxyDB
from src.shared import init_db, init_sqlite_db

CONCURRENT_TASKS = 100


def to_project_relative_path(path: Any) -> str | None:
    if not path or not isinstance(path, str):
        return path
    if isinstance(path, Path):
        path = str(path)

    project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
    return os.path.relpath(os.path.abspath(path), project_root)


async def process_proxy(
    db: ProxyDB,
    data: dict[str, Any],
    driver: str,
    semaphore: asyncio.Semaphore,
):
    async with semaphore:
        proxy = str(data.get("proxy", ""))
        try:
            normalized_proxy = db.normalize_proxy(proxy)
        except ValueError:
            normalized_proxy = proxy.strip()

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
                    await asyncio.to_thread(
                        db.update_data,
                        extracted_proxy,
                        new_data,
                    )
                except Exception as e:
                    print(f"[{driver}] Failed to update proxy in database: {e}")

                await asyncio.to_thread(db.remove, proxy)
                return

            print(
                f"[{driver}] Invalid proxy: "
                f"{red(proxy)} -> Normalized: {red(normalized_proxy)}"
            )

            await asyncio.to_thread(db.remove, proxy)
            return

        # Needs normalization
        if proxy != normalized_proxy:
            print(
                f"[{driver}] Invalid proxy format: "
                f"{red(proxy)} -> Normalized: {green(normalized_proxy)}"
            )

            await asyncio.to_thread(db.remove, proxy)

            new_data = data.copy()
            new_data["proxy"] = normalized_proxy

            try:
                await asyncio.to_thread(
                    db.update_data,
                    normalized_proxy,
                    new_data,
                )
            except Exception as e:
                print(f"[{driver}] Failed to update proxy in database: {e}")


async def remover(db: ProxyDB):
    page = 1
    per_page = 1000

    driver = (
        f"{db.driver} "
        f"{f'({to_project_relative_path(db.db_location)})' if db.driver == 'sqlite' else ''}"
    ).strip()

    semaphore = asyncio.Semaphore(CONCURRENT_TASKS)

    while True:
        proxies = await asyncio.to_thread(
            db.get_all_proxies,
            page=page,
            per_page=per_page,
        )

        if not proxies:
            break

        tasks = [
            process_proxy(
                db=db,
                data=data,
                driver=driver,
                semaphore=semaphore,
            )
            for data in proxies
        ]

        await asyncio.gather(*tasks)

        page += 1


async def main():
    databases = [
        init_db(),
        init_sqlite_db(get_relative_path("src/database.sqlite")),
        init_sqlite_db(get_relative_path("tmp/database.sqlite")),
    ]

    try:
        await asyncio.gather(*(remover(db) for db in databases))

    finally:
        close_tasks = []

        for db in databases:
            close_tasks.append(asyncio.to_thread(close_database, db))

        await asyncio.gather(*close_tasks)


def close_database(db: ProxyDB):
    try:
        db.close()
    except Exception as e:
        print(f"[{db.driver}] Error occurred while closing database: {e}")


if __name__ == "__main__":
    asyncio.run(main())
