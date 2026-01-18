import argparse
import os
import sys
from typing import List, Sequence, Dict, Any

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from proxy_hunter import extract_proxies, is_port_open
from src.ASNLookup import ASNLookup
from src.func import get_relative_path
from src.shared import init_db, init_readonly_db
from src.utils.file.FileLockHelper import FileLockHelper
from src.func_platform import is_debug
from src.func_console import ConsoleColor, red, green, yellow, magenta

current_filename = os.path.basename(__file__)
locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
if not locker.lock():
    print(red("Another instance is running. Exiting."))
    sys.exit(0)

# CLI args: include --production to use readonly DB, plus other flags
parser = argparse.ArgumentParser()
parser.add_argument(
    "--production",
    action="store_true",
    help="Use readonly production database (init_readonly_db)",
)
parser.add_argument(
    "--include-untested",
    action="store_true",
    help="Include untested proxies when selecting duplicates",
)
parser.add_argument(
    "--limit",
    type=int,
    default=None,
    help="Limit number of duplicate IPs to fetch (overrides default batch)",
)
args = parser.parse_args()

db = init_readonly_db() if args.production else init_db()
is_mysql = db.driver == "mysql"
if not db.db:
    print(red("Database not initialized. Exiting."))
    sys.exit(1)

if is_mysql:
    substrFunction = "SUBSTRING_INDEX(proxy, ':', 1)"
    randomFunction = "RAND()"
else:
    substrFunction = "SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)"
    randomFunction = "RANDOM()"

# parameter placeholder depending on driver
ph = "%s" if is_mysql else "?"

# Build status filter depending on CLI flag
include_untested = args.include_untested

status_filter_inner = (
    "WHERE status != 'active'"
    if include_untested
    else "WHERE status != 'active' AND status != 'untested'"
)

status_filter_trailing = (
    "AND status != 'active'"
    if include_untested
    else "AND status != 'active' AND status != 'untested'"
)

sql_duplicate_ips = f"""
SELECT ip, COUNT(*) AS count_duplicates
    FROM (
        SELECT {substrFunction} AS ip
        FROM proxies
        {status_filter_inner}
    ) AS filtered_proxies
    GROUP BY ip
    HAVING COUNT(*) > 1
    ORDER BY {randomFunction}
    LIMIT {ph} OFFSET {ph}
"""

# paging: use --limit if provided, else default batch size
default_batch = 1000
batch = args.limit if args.limit is not None else default_batch
offset = 0

res = db.db.execute_query_fetch(sql_duplicate_ips, (batch, offset))
if isinstance(res, list):
    duplicate_ips = res
    print(green(f"Found {len(duplicate_ips)} duplicate IPs."))
    for entry in duplicate_ips:
        ip = entry["ip"]
        count_duplicates = entry["count_duplicates"]
        print(magenta(f"IP: {ip}, Duplicates: {count_duplicates}"))

        # Fetch all proxies with this IP that match status filter
        sql_proxies_with_ip = f"""
        SELECT id, proxy
        FROM proxies
        WHERE {substrFunction} = {ph}
        {status_filter_trailing}
        ORDER BY {randomFunction}
        LIMIT {ph}
        """
        proxies_with_ip = db.db.execute_query_fetch(sql_proxies_with_ip, (ip, batch))
        if isinstance(proxies_with_ip, list):
            # Keep one random proxy, delete the rest (by proxy string) only if their ports are closed
            proxies_to_delete = []
            for idx, proxy_entry in enumerate(proxies_with_ip):
                proxy_id = proxy_entry["id"]
                proxy_str = proxy_entry["proxy"]
                print(yellow(f"  [{idx+1}] ID: {proxy_id}, Proxy: {proxy_str}"))
                if is_port_open(proxy_str):
                    print(
                        green(
                            f"    Port is open for proxy {proxy_str}, skipping deletion."
                        )
                    )
                else:
                    proxies_to_delete.append(proxy_str)
                    print(
                        red(
                            f"    Port is closed for proxy {proxy_str}, marked for deletion."
                        )
                    )

            is_proxies_total_same_as_duplicates = (
                len(proxies_to_delete) == count_duplicates
            )
            if is_proxies_total_same_as_duplicates and len(proxies_to_delete) > 0:
                # Ensure at least one proxy remains if all are to be deleted
                proxies_to_delete.pop()
                print(
                    yellow(
                        "    All proxies had closed ports; kept one to avoid deleting all."
                    )
                )

            if len(proxies_to_delete) > 0:
                print(
                    yellow(
                        f"Would delete {len(proxies_to_delete)} duplicate proxies for IP {ip}."
                    )
                )
                print(yellow(f"Proxies to delete: {proxies_to_delete}"))
                # Delete the duplicates using ProxyDB.remove(proxy)
                deleted_count = 0
                for proxy_val in proxies_to_delete:
                    try:
                        db.remove(proxy_val)
                        deleted_count += 1
                    except Exception as e:
                        print(red(f"    Failed to delete proxy {proxy_val}: {e}"))
                print(green(f"    Deleted {deleted_count} proxies for IP {ip}."))
            else:
                print(yellow(f"    No proxies to delete for IP {ip}."))
