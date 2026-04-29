import os
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any, Dict, List

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from proxy_hunter import check_proxy, is_port_open
from src.ProxyDB import ProxyDB
from src.database.SQLiteMarker import SQLiteMarker
from src.func import get_relative_path
from src.func_console import green, magenta, red, yellow
from src.func_date import get_current_rfc3339_time
from src.shared import init_db
from src.utils.parse_args import parse_args
from src.utils.file.FileLockHelper import FileLockHelper

current_filename = os.path.basename(__file__)
PROTOCOL_ENDPOINT = "http://httpforever.com/"
PROTOCOLS = ("http", "socks4", "socks5")
MAX_CHECK_WORKERS = 8


def _probe_proxy(proxy_entry: Dict[str, Any]) -> Dict[str, Any]:
    proxy_str = proxy_entry["proxy"]
    result: Dict[str, Any] = {
        "proxy": proxy_str,
        "port_open": False,
        "working_protocols": [],
    }

    if not is_port_open(proxy_str):
        return result

    checks = {
        proto: check_proxy(
            proxy=proxy_str, proxy_type=proto, endpoint=PROTOCOL_ENDPOINT
        )
        for proto in PROTOCOLS
    }
    working_protocols = [
        getattr(check, "type", proto) for proto, check in checks.items() if check.result
    ]
    result["port_open"] = True
    result["working_protocols"] = working_protocols
    return result


def _delete_proxies(db: ProxyDB, proxies_to_delete: List[str]) -> int:
    unique_proxies = list(dict.fromkeys(proxies_to_delete))
    if not unique_proxies:
        return 0

    placeholders = ", ".join(
        ["%s" if db.driver == "mysql" else "?"] * len(unique_proxies)
    )
    db.get_db().execute_query(
        f"DELETE FROM proxies WHERE proxy IN ({placeholders})",
        unique_proxies,
    )
    return len(unique_proxies)


def main() -> int:
    # Use shared parser: supports --limit/--max and --uid
    args = parse_args(default_limit=100, description="Filter Duplicate IPs")

    lock_name = args.uid if args.uid else os.path.basename(__file__)
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{lock_name}.lock"))
    if not locker.lock():
        print(red("Another instance is running. Exiting."))
        return 0

    db = None
    marker = None
    try:
        # Always use init_db for a writable DB in this script
        db = init_db("mysql")

        if not db.db:
            print(red("Database not initialized. Exiting."))
            return 1

        marker = SQLiteMarker(
            db_filename="filter_duplicate_ips.sqlite",
            table_name="checked_proxies",
            key_column="proxy",
            base_dir="tmp/database",
        )

        is_mysql = db.driver == "mysql"
        substr_function = (
            "SUBSTRING_INDEX(proxy, ':', 1)"
            if is_mysql
            else "SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)"
        )
        random_function = "RAND()" if is_mysql else "RANDOM()"
        placeholder = "%s" if is_mysql else "?"

        # Always include untested proxies
        status_filter_inner = "WHERE status != 'active'"
        status_filter_trailing = "AND status != 'active'"

        sql_duplicate_ips = f"""
        SELECT ip, COUNT(*) AS count_duplicates
            FROM (
                SELECT {substr_function} AS ip
                FROM proxies
                {status_filter_inner}
            ) AS filtered_proxies
            GROUP BY ip
            HAVING COUNT(*) > 1
            ORDER BY {random_function}
            LIMIT {placeholder} OFFSET {placeholder}
        """

        default_batch = 1000
        batch = (
            args.limit if getattr(args, "limit", None) is not None else default_batch
        )
        offset = 0

        res = db.db.execute_query_fetch(sql_duplicate_ips, (batch, offset))
        if not isinstance(res, list):
            return 0

        duplicate_ips = res
        print(green(f"Found {len(duplicate_ips)} duplicate IPs."))

        for entry in duplicate_ips:
            ip = entry["ip"]
            count_duplicates = entry["count_duplicates"]
            print(magenta(f"IP: {ip}, Duplicates: {count_duplicates}"))

            sql_proxies_with_ip = f"""
            SELECT id, proxy, status
            FROM proxies
            WHERE {substr_function} = {placeholder}
            {status_filter_trailing}
            ORDER BY {random_function}
            LIMIT {placeholder}
            """
            proxies_with_ip = db.db.execute_query_fetch(
                sql_proxies_with_ip, (ip, batch)
            )
            if not isinstance(proxies_with_ip, list) or not proxies_with_ip:
                print(yellow(f"    No proxies to process for IP {ip}."))
                continue

            proxy_by_value: Dict[str, Dict[str, Any]] = {}
            ordered_proxy_values: List[str] = []
            for proxy_row in proxies_with_ip:
                proxy_value = str(proxy_row.get("proxy") or "").strip()
                if not proxy_value or proxy_value in proxy_by_value:
                    continue
                proxy_by_value[proxy_value] = proxy_row
                ordered_proxy_values.append(proxy_value)

            pending_proxy_values, already_checked = marker.filter_unseen(
                ordered_proxy_values
            )
            proxies_to_process = [
                proxy_by_value[value] for value in pending_proxy_values
            ]
            if already_checked:
                print(
                    yellow(
                        f"    Skipped {already_checked} already-checked proxies for IP {ip}."
                    )
                )

            if not proxies_to_process:
                print(yellow(f"    No new proxies to process for IP {ip}."))
                continue

            proxies_to_delete: List[str] = []
            worker_count = min(MAX_CHECK_WORKERS, len(proxies_to_process))
            with ThreadPoolExecutor(max_workers=max(1, worker_count)) as executor:
                future_map = {
                    executor.submit(_probe_proxy, proxy_entry): proxy_entry
                    for proxy_entry in proxies_to_process
                }

                for idx, future in enumerate(as_completed(future_map), start=1):
                    proxy_entry = future_map[future]
                    proxy_id = proxy_entry["id"]
                    proxy_str = proxy_entry["proxy"]
                    proxy_status = proxy_entry["status"]
                    print(yellow(f"  [{idx}] ID: {proxy_id}, Proxy: {proxy_str}"))

                    try:
                        probe = future.result()
                    except Exception as exc:
                        print(red(f"    Failed to probe proxy {proxy_str}: {exc}"))
                        proxies_to_delete.append(proxy_str)
                        marker.mark(proxy_str, 30)
                        continue

                    if probe["port_open"]:
                        working_protocols = probe["working_protocols"]
                        if working_protocols:
                            print(
                                green(
                                    f"    Port is open for proxy {proxy_str}, working protocols: {working_protocols}. Keeping this proxy."
                                )
                            )
                            db.update_data(
                                proxy=proxy_str,
                                data={
                                    "status": "active",
                                    "type": "-".join(working_protocols),
                                    "last_check": get_current_rfc3339_time(),
                                },
                            )
                        else:
                            proxies_to_delete.append(proxy_str)
                            print(
                                red(
                                    f"    Port is open for proxy {proxy_str} but no protocols work, marked for deletion."
                                )
                            )
                    else:
                        proxies_to_delete.append(proxy_str)
                        print(
                            red(
                                f"    Port is closed for proxy {proxy_str}, marked for deletion."
                            )
                        )

                    marker.mark(proxy_str, 30)

            if len(proxies_to_delete) == len(proxies_to_process) and proxies_to_delete:
                proxies_to_delete.pop()
                print(
                    yellow(
                        "    All proxies had closed ports; kept one to avoid deleting all."
                    )
                )

            if proxies_to_delete:
                print(
                    yellow(
                        f"Would delete {len(proxies_to_delete)} duplicate proxies for IP {ip}."
                    )
                )
                print(yellow(f"Proxies to delete: {proxies_to_delete}"))
                deleted_count = _delete_proxies(db, proxies_to_delete)
                print(green(f"    Deleted {deleted_count} proxies for IP {ip}."))
            else:
                print(yellow(f"    No proxies to delete for IP {ip}."))

        return 0
    finally:
        if db:
            db.close()
        if marker:
            marker.close()
        locker.unlock()


if __name__ == "__main__":
    raise SystemExit(main())
