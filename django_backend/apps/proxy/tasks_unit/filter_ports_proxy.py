import concurrent.futures
import os
import random
import sys
import threading
from typing import Callable, Dict, List, Optional, Union

from joblib import Parallel, delayed

from django_backend.apps.core.management.commands.check_proxy_real import real_check

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)
from django.db import connection

# result_log_file = get_relative_path("tmp/logs/filter-ports.txt")
from django_backend.apps.proxy.tasks_unit.real_check_proxy import result_log_file
from src.func_console import green, log_file, red
from src.func_date import get_current_rfc3339_time
from src.func_proxy import is_port_open


def fetch_proxies_same_ip(
    status: List[str] = ["dead", "port-closed", "untested"]
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
        WHERE {condition}
        GROUP BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
        HAVING COUNT(*) > 1
    )
    ORDER BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
    """

    # Execute the query
    with connection.cursor() as cursor:
        cursor.execute(query)
        proxies = cursor.fetchall()

    # Process the results
    result: Dict[str, List[str]] = {}
    for row in proxies:
        proxy = row[0]
        ip = proxy.split(":")[0]
        if ip not in result:
            result[ip] = []
        result[ip].append(proxy)

    log_file(result_log_file, f"got duplicated {len(result)} ips")

    return result


def filter_duplicates_ips(max: int = 10, callback: Optional[Callable] = None):
    duplicates_ips = fetch_proxies_same_ip()

    def process_ip(ip, ip_proxies):
        with connection.cursor() as cursor:
            log_file(result_log_file, f"{ip} has {len(ip_proxies)} duplicates")

            if len(ip_proxies) > 1:
                # Fetch all rows matching the IP address (excluding active and port-open proxies)
                cursor.execute(
                    """
                    SELECT rowid, * FROM proxies
                    WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) = %s
                    AND status != 'active' AND status != 'port-open'
                    ORDER BY RANDOM() LIMIT 49999;
                    """,
                    [ip],
                )
                ip_rows = cursor.fetchall()

                if len(ip_rows) > 1:
                    keep_row = ip_rows[0]
                    random.shuffle(ip_rows)

                    for row in ip_rows:
                        proxy = row[2]  # Adjust index based on column position
                        if is_port_open(proxy):
                            log_file(result_log_file, f"{proxy} {green('port open')}")
                            # Keep open port
                            keep_row = row
                            # Set status to port-open
                            cursor.execute(
                                "UPDATE proxies SET last_check = %s, status = %s WHERE proxy = %s",
                                (get_current_rfc3339_time(), "port-open", proxy),
                            )
                            connection.commit()
                        else:
                            log_file(result_log_file, f"{proxy} {red('port closed')}")
                            if keep_row[2] != proxy:
                                cursor.execute(
                                    "DELETE FROM proxies WHERE proxy = %s", [proxy]
                                )
                                connection.commit()

    try:
        with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
            futures = []
            for index, (ip, ip_proxies) in enumerate(duplicates_ips.items()):
                if index >= max:
                    break
                futures.append(executor.submit(process_ip, ip, ip_proxies))

            for future in concurrent.futures.as_completed(futures):
                try:
                    future.result()  # Ensure all threads complete
                except Exception as e:
                    log_file(result_log_file, f"Error processing IP: {e}")
    except Exception as e:
        log_file(result_log_file, f"filter_duplicates_ips fail create thread: {e}")
    if callable(callback):
        callback()


def start_filter_duplicates_ips():
    thread = threading.Thread(target=filter_duplicates_ips, args=(10,))
    thread.start()
    return thread


def fetch_open_ports(max: int = 10) -> List[Dict[str, Union[str, None]]]:
    # Use Django's connection to create a cursor
    with connection.cursor() as cursor:
        # Execute the query to fetch proxies with status 'port-open'
        cursor.execute("SELECT proxy FROM proxies WHERE status = 'port-open'")

        # Fetch all results
        proxies = cursor.fetchall()

        # Fetch column names from the cursor description
        column_names = [desc[0] for desc in cursor.description]

        # Convert the result into a list of dictionaries
        proxies_dict: List[Dict[str, Union[str, None]]] = [
            dict(zip(column_names, proxy)) for proxy in proxies
        ][:max]

    return proxies_dict


def worker_check_open_ports(item: Dict[str, str]):
    try:
        # Use Django's database connection to create a cursor
        with connection.cursor() as cursor:
            test = real_check(
                item["proxy"], "https://www.axis.co.id/bantuan", "pusat layanan"
            )
            if not test["result"]:
                test = real_check(item["proxy"], "https://www.example.com/", "example")

            if not test["result"]:
                test = real_check(item["proxy"], "http://azenv.net/", "AZ Environment")

            if not test["result"]:
                test = real_check(
                    item["proxy"], "http://httpforever.com/", "HTTP Forever"
                )

            https = "true" if test["https"] else "false"
            protocols = "-".join(test["type"]).lower() if "type" in test else ""
            if test["result"]:
                cursor.execute(
                    """
                    UPDATE proxies
                    SET last_check = %s, status = %s, https = %s, type = %s
                    WHERE proxy = %s
                    """,
                    (
                        get_current_rfc3339_time(),
                        "active",
                        https,
                        protocols,
                        item["proxy"],
                    ),
                )
                log_file(
                    result_log_file,
                    f"{item['proxy']} from status=port-open {green('working')}",
                )
            else:
                # cursor.execute(
                #     """
                #     UPDATE proxies
                #     SET last_check = %s, status = %s
                #     WHERE proxy = %s
                #     """,
                #     (get_current_rfc3339_time(), "dead", item["proxy"]),
                # )
                cursor.execute("DELETE FROM proxies WHERE proxy = %s", [item["proxy"]])
                log_file(
                    result_log_file,
                    f"{item['proxy']} from status=port-open {red('dead')}",
                )

            connection.commit()

    except Exception as e:
        log_file(result_log_file, f"worker_check_open_ports Error occurred: {e}")


def check_open_ports(max: int = 10, callback: Optional[Callable] = None):
    """
    check proxy WHERE status = 'port-open'
    """
    proxies_dict = fetch_open_ports(max)
    if proxies_dict:
        proxies_dict = proxies_dict[
            1:
        ]  # Exclude the first item (when dead will be deleted in worker_check_open_ports)
        try:
            with concurrent.futures.ThreadPoolExecutor(max_workers=10) as executor:
                futures = []
                for index, item in enumerate(proxies_dict):
                    if index >= max:
                        break
                    futures.append(executor.submit(worker_check_open_ports, item))

                for future in concurrent.futures.as_completed(futures):
                    try:
                        future.result()  # Ensure all threads complete
                    except Exception as e:
                        log_file(result_log_file, f"Error processing {item}: {e}")
        except Exception as e:
            log_file(result_log_file, f"check_open_ports fail create thread: {e}")
    if callable(callback):
        callback()


def start_check_open_ports():
    thread = threading.Thread(target=check_open_ports, args=(10,))
    thread.start()
    return thread
