import os
import sys
import asyncio
import inspect
from typing import Any, Dict, List, Tuple

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from src.func_console import cyan
from proxy_hunter import clash_verge_generate_yaml
from src.utils.parse_args import ParseArgs, parse_args
from src.func_date import (
    get_yesterday_rfc3339_time,
    is_date_rfc3339_older_than,
)
from src.shared import init_readonly_db
from artisan.proxy_getter import normalize_proxy_value
from artisan.proxy_https_checker import check_proxy_https, check_proxy_applied
from artisan.filter_duplicate_ips import check_proxy_http

concurrency = 4
timeout = 10


def call_db_without_limit(method, **kwargs):
    """
    Call DB method without applying a proxy limit.
    If the method supports `limit`, pass None.
    """
    signature = inspect.signature(method)

    if "limit" in signature.parameters:
        kwargs["limit"] = None

    return method(**kwargs)


def is_working_result(result: Dict[str, Any]) -> bool:
    return bool(
        result.get("result")
        or result.get("https")
        or result.get("applied")
        or result.get("status") == "active"
    )


async def check_single_proxy(
    entry: Dict[str, Any],
) -> Tuple[Dict[str, Any], Dict[str, Any]]:
    proxy_val = str(entry.get("proxy") or "").strip()

    if not proxy_val:
        return entry, {
            "proxy": None,
            "type": None,
            "https": False,
            "applied": False,
            "protocol_ok": False,
            "status": "dead",
            "result": False,
            "private": False,
        }

    original = normalize_proxy_value(entry)
    protocols = ["socks5", "socks4", "http"]
    private_seen = False

    for proto in protocols:
        proxy_url = f"{proto}://{original}"

        http_check = await check_proxy_http(
            proxy=proxy_url,
            timeout=timeout,
            url="http://httpforever.com/",
            expected_title="HTTP Forever",
        )

        protocol_result = bool(http_check.get("result", False))
        is_private = bool(http_check.get("private", False))
        private_seen = private_seen or is_private

        supports_https = False
        applied = False

        if protocol_result:
            supports_https = await check_proxy_https(
                proxy=proxy_url,
                timeout=timeout,
                url="https://www.yahoo.com/",
                expected_title="Yahoo",
            )

            applied = await check_proxy_applied(
                proxy=proxy_url,
                timeout=timeout,
            )

        if protocol_result or applied:
            return entry, {
                "proxy": proxy_val,
                "type": proto,
                "https": bool(supports_https),
                "applied": bool(applied),
                "protocol_ok": bool(protocol_result),
                "status": "active",
                "result": bool(protocol_result),
                "private": bool(private_seen),
            }

    return entry, {
        "proxy": proxy_val,
        "type": None,
        "https": False,
        "applied": False,
        "protocol_ok": False,
        "status": "dead",
        "result": False,
        "private": bool(private_seen),
    }


async def run_checks_for_proxies(
    args: ParseArgs,
) -> List[Tuple[Dict[str, Any], Dict[str, Any]]]:
    db = init_readonly_db()

    def custom_filter(rows: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        return [
            row
            for row in rows
            if isinstance(row, dict)
            and (row.get("https") or "").lower() != "true"
            and (
                not row.get("last_check")
                or is_date_rfc3339_older_than(row.get("last_check"), hours=24)
            )
        ]

    proxies = custom_filter(call_db_without_limit(db.get_untested_proxies))

    if not proxies:
        proxies = custom_filter(call_db_without_limit(db.get_all_proxies))

    if not proxies:
        return []

    queue: asyncio.Queue[Dict[str, Any]] = asyncio.Queue()
    for proxy in proxies:
        queue.put_nowait(proxy)

    stop_event = asyncio.Event()
    results: List[Tuple[Dict[str, Any], Dict[str, Any]]] = []
    result_lock = asyncio.Lock()

    async def worker(worker_id: int):
        while not stop_event.is_set():
            try:
                entry = queue.get_nowait()
            except asyncio.QueueEmpty:
                return

            try:
                if stop_event.is_set():
                    return

                checked_result = await check_single_proxy(entry)

                async with result_lock:
                    results.append(checked_result)

                _, result = checked_result

                if is_working_result(result):
                    print(
                        f"Working proxy found: {result.get('type')}://{result.get('proxy')}"
                    )
                    stop_event.set()
                    return

            except asyncio.CancelledError:
                raise

            except Exception as exc:
                proxy_val = str(entry.get("proxy") or "").strip()

                async with result_lock:
                    results.append(
                        (
                            entry,
                            {
                                "proxy": proxy_val,
                                "type": None,
                                "https": False,
                                "applied": False,
                                "protocol_ok": False,
                                "status": "dead",
                                "result": False,
                                "private": False,
                                "error": str(exc),
                            },
                        )
                    )

                print(f"Error checking proxy {proxy_val}: {exc}")

            finally:
                queue.task_done()

    worker_count = min(concurrency, len(proxies))
    workers = [asyncio.create_task(worker(i + 1)) for i in range(worker_count)]

    try:
        await asyncio.wait(workers, return_when=asyncio.FIRST_COMPLETED)

        if stop_event.is_set():
            for task in workers:
                if not task.done():
                    task.cancel()

            await asyncio.gather(*workers, return_exceptions=True)
        else:
            await asyncio.gather(*workers)

    finally:
        for task in workers:
            if not task.done():
                task.cancel()

        await asyncio.gather(*workers, return_exceptions=True)

    return results


async def main(args):
    print("Starting CI checker...")

    results = await run_checks_for_proxies(args)
    db = init_readonly_db()

    try:
        for entry, result in results:
            proxy_val = str(result.get("proxy") or "").strip()

            if not proxy_val:
                continue

            update_data = {
                "status": result.get("status") or "dead",
                "private": "true" if result.get("private") else "false",
            }

            if result.get("status") == "active":
                if result.get("type"):
                    update_data["type"] = result.get("type")

                if result.get("https"):
                    update_data["https"] = "true"

                if result.get("private"):
                    update_data["private"] = "true"

            try:
                db.update_data(
                    proxy=proxy_val,
                    data=update_data,
                    update_time=True,
                    debug=True,
                )
            except Exception as exc:
                print(f"Error updating proxy {proxy_val}: {exc}")

        working_count = sum(1 for _, result in results if is_working_result(result))

        print(f"Checked proxies before stop: {len(results)}")
        print(f"Working proxies found: {working_count}")

    finally:
        # Save working proxies to file
        output_file = get_relative_path("working.json")

        call_db_without_limit(
            db.get_working_proxies,
            output_file=output_file,
            last_checked=get_yesterday_rfc3339_time(),
        )

        print(f"Saved working proxies to {cyan(output_file)}")

        # Generate clash verge config for working proxies
        working_proxies = db.get_working_proxies(
            output_file=output_file, last_checked=get_yesterday_rfc3339_time()
        )
        clash_output_file = get_relative_path("tmp/proxies/clash-verge.yaml")

        clash_result = clash_verge_generate_yaml(
            working_proxies=working_proxies or [],
            output_file=clash_output_file,
        )

        print(
            f"Saved Clash Verge config to {cyan(clash_output_file)} "
            f"with {clash_result['proxy_count']} proxies"
        )

        if clash_result["skipped_count"]:
            print(
                f"Skipped {clash_result['skipped_count']} proxies "
                f"because they are unsupported or invalid for Clash config"
            )


if __name__ == "__main__":
    args = parse_args(default_limit=0)
    asyncio.run(main(args))
