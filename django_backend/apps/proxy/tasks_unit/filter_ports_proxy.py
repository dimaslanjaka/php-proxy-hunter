import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)
import concurrent.futures
import random
import threading
from typing import Callable, Dict, List, Optional, Set, Union

from django.conf import settings
from django.db import connection
from proxy_hunter import is_valid_proxy

from django_backend.apps.proxy.tasks_unit.real_check_proxy import (
    real_check_proxy,
    result_log_file,
    global_tasks as proxy_checker_threads,
)
from django_backend.apps.proxy.utils import execute_select_query, execute_sql_query
from src.func_console import green, log_file, magenta, orange, red
from src.func_date import get_current_rfc3339_time
from proxy_hunter import is_port_open

global_tasks: Set[Union[threading.Thread, concurrent.futures.Future]] = set()


def cleanup_threads():
    global global_tasks
    global_tasks = {
        task
        for task in global_tasks
        if (isinstance(task, threading.Thread) and not task.is_alive())
        or (isinstance(task, concurrent.futures.Future) and task.done())
    }


def fetch_proxies_same_ip(
    status: List[str] = ["dead", "port-closed", "untested"], limit: int = sys.maxsize
) -> Dict[str, List[str]]:
    # Create a condition string from the status list
    condition = " OR ".join([f"status = '{s}'" for s in status])

    # Define the query to find proxies with the same IP but different ports
    query = f"""
    SELECT proxy
    FROM proxies
    WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) IN (
        SELECT SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
        FROM proxies
        WHERE {condition} OR status IS NULL
        GROUP BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
        HAVING COUNT(*) > 1
    )
    ORDER BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1), RANDOM()
    LIMIT {limit}
    """

    # Execute the query
    proxies = execute_select_query(query)

    # Process the results
    result: Dict[str, List[str]] = {}
    for row in proxies:
        proxy = row["proxy"]
        ip = proxy.split(":")[0]
        if ip not in result:
            result[ip] = []
        result[ip].append(proxy)

    # Filtering dictionary to include only key-value pairs where the list has more than 2 items
    filtered_result = {k: v for k, v in result.items() if len(v) > 2}

    # Shuffle the items in the filtered_result
    items = list(filtered_result.items())  # Convert to a list of tuples
    random.shuffle(items)  # Shuffle the list of tuples

    # Reconstruct the dictionary with shuffled items
    shuffled_result = dict(items)

    return shuffled_result


def filter_duplicates_ips(limit: int = 10, callback: Optional[Callable] = None):
    duplicates_ips = fetch_proxies_same_ip(limit=limit)

    def process_ip(ip: str, ip_proxies: List[str]):
        # log_file(result_log_file, f"{ip} has {len(ip_proxies)} duplicates")
        random.shuffle(ip_proxies)
        if len(ip_proxies) > 1:
            # Fetch all rows matching the IP address (excluding active and port-open proxies)
            # Set duplicated limit [n]
            ip_rows = execute_select_query(
                f"""
                SELECT rowid, * FROM proxies
                WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) = ?
                AND (status != 'active' AND status != 'port-open' OR status IS NULL)
                ORDER BY RANDOM() LIMIT 50;
                """,
                (ip,),
            )
            log_file(
                result_log_file,
                f"[FILTER-PORT] {ip} has {len(ip_proxies)} duplicates, inactive rows {len(ip_rows)}",
            )

            if len(ip_rows) > 1:
                keep_proxy = None
                random.shuffle(ip_rows)

                for row in ip_rows:
                    proxy = row["proxy"]
                    if not proxy:
                        continue
                    if not keep_proxy:
                        keep_proxy = proxy
                    valid = is_valid_proxy(proxy)
                    if is_port_open(proxy) and valid:
                        log_file(
                            result_log_file,
                            f"[FILTER-PORT] {proxy} \t {green('port open')}",
                        )
                        keep_proxy = proxy
                        # Set status to port-open
                        execute_sql_query(
                            "UPDATE proxies SET last_check = ?, status = ? WHERE proxy = ?",
                            (get_current_rfc3339_time(), "port-open", proxy),
                        )
                    else:
                        if not valid:
                            removed = red("invalid [DELETED]")
                        elif keep_proxy != proxy:
                            removed = orange("[DELETED]")
                        else:
                            removed = magenta("[SKIPPED]")
                        log_file(
                            result_log_file,
                            f"[FILTER-PORT] {proxy} \t {red('port closed')} \t {removed}",
                        )
                        if keep_proxy != proxy or not valid:
                            execute_sql_query(
                                "DELETE FROM proxies WHERE proxy = ?", (proxy,)
                            )

    if len(duplicates_ips) > 0:
        try:
            with concurrent.futures.ThreadPoolExecutor(
                max_workers=settings.WORKER_THREADS
            ) as executor:
                futures = []
                for index, (ip, ip_proxies) in enumerate(duplicates_ips.items()):
                    if index >= limit:
                        break
                    futures.append(executor.submit(process_ip, ip, ip_proxies))

                for future in concurrent.futures.as_completed(futures):
                    try:
                        future.result()  # Ensure all threads complete
                    except Exception as e:
                        log_file(
                            result_log_file, f"[FILTER-PORT] Error processing IP: {e}"
                        )
                log_file(
                    result_log_file,
                    f"[FILTER-PORT] Processing {ip} with {len(ip_proxies)} proxies done",
                )
        except Exception as e:
            log_file(result_log_file, f"[FILTER-PORT] fail create thread: {e}")
    if callable(callback):
        callback()


def start_filter_duplicates_ips():
    # start checking [n] open ports from duplicated ips
    thread = threading.Thread(
        target=filter_duplicates_ips, args=(settings.LIMIT_FILTER_CHECK,)
    )
    thread.start()
    return thread


def fetch_open_ports(limit: int = 10) -> List[Dict[str, Union[str, None]]]:
    # Use Django's connection to create a cursor
    with connection.cursor() as cursor:
        # Execute the query to fetch proxies with status 'port-open'
        cursor.execute(
            f"SELECT proxy FROM proxies WHERE status = 'port-open' ORDER BY RANDOM() LIMIT {limit}"
        )

        # Fetch all results
        proxies = cursor.fetchall()

        # Fetch column names from the cursor description
        column_names = [desc[0] for desc in cursor.description]

        # Convert the result into a list of dictionaries
        proxies_dict: List[Dict[str, Union[str, None]]] = [
            dict(zip(column_names, proxy)) for proxy in proxies
        ][:limit]

    return proxies_dict


def worker_check_open_ports(item: Dict[str, str]):
    try:
        # log_file(result_log_file, f"testing {item} status=port-open")
        # Use Django's database connection to create a cursor
        with connection.cursor() as cursor:
            tests = {
                "http": real_check_proxy(item["proxy"], "http"),
                "socks4": real_check_proxy(item["proxy"], "socks4"),
                "socks5": real_check_proxy(item["proxy"], "socks5"),
            }
            # Filter dictionary to include only entries where `result` is True
            filtered_tests = {
                key: value for key, value in tests.items() if value.result
            }
            protocols = "-".join(filtered_tests.keys()).lower()
            has_https = any(value.https for value in filtered_tests.values())
            highest_latency_entry = max(
                filtered_tests.values(), key=lambda x: x.latency, default=None
            )
            if highest_latency_entry:
                latency = int(highest_latency_entry.latency)
            else:
                latency = 0

            if filtered_tests:
                https = "true" if has_https else "false"
                execute_sql_query(
                    """
                    UPDATE proxies
                    SET last_check = ?, status = ?, https = ?, type = ?, latency = ?
                    WHERE proxy = ?
                    """,
                    (
                        get_current_rfc3339_time(),
                        "active",
                        https,
                        protocols,
                        str(latency),
                        item["proxy"],
                    ),
                )
                log_file(
                    result_log_file,
                    f"[FILTER-PORT] {item['proxy']} from status=port-open {green('working')}",
                )
            else:
                execute_sql_query(
                    "DELETE FROM proxies WHERE proxy = ?", (item["proxy"],)
                )
                log_file(
                    result_log_file,
                    f"[FILTER-PORT] {item['proxy']} from status=port-open {red('dead')}",
                )

            connection.commit()

    except Exception as e:
        log_file(result_log_file, f"[FILTER-PORT] Error occurred: {e}")


def check_open_ports(limit: int = 10, callback: Optional[Callable] = None):
    """
    check proxy WHERE status = 'port-open'
    """
    proxies_dict = fetch_open_ports(limit)
    if proxies_dict:
        proxies_dict = proxies_dict[
            1:
        ]  # Exclude the first item (when dead will be deleted in worker_check_open_ports)
        try:
            with concurrent.futures.ThreadPoolExecutor(
                max_workers=settings.WORKER_THREADS
            ) as executor:
                futures = []
                for item in proxies_dict[:limit]:
                    futures.append(executor.submit(worker_check_open_ports, item))
                proxy_checker_threads.update(futures)

                for future in concurrent.futures.as_completed(futures):
                    try:
                        future.result()  # Ensure all threads complete
                    except Exception as e:
                        log_file(
                            result_log_file,
                            f"[FILTER-PORT] Error processing {item}: {e}",
                        )
        except Exception as e:
            log_file(result_log_file, f"[FILTER-PORT] fail create thread: {e}")
    if callable(callback):
        callback()


def start_check_open_ports():
    # start checking [n] proxies with status=port-open
    thread = threading.Thread(
        target=check_open_ports, args=(settings.LIMIT_FILTER_CHECK,)
    )
    thread.daemon = True  # Allow thread to be killed when main program exits
    thread.start()
    return thread
