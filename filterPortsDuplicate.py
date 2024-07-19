import argparse
import random
import sqlite3
from datetime import datetime, timedelta
from sqlite3 import Cursor
from typing import Callable, Dict, List, Optional, Union
import concurrent.futures
from joblib import Parallel, delayed

from proxyCheckerReal import real_check
from src.func import get_relative_path
from src.func_console import green, red
from src.func_proxy import is_port_open
from src.ProxyDB import ProxyDB

# remove duplicate ip's more than 3 proxies

db = ProxyDB(get_relative_path("src/database.sqlite"), True)
conn = db.db.conn
cursor = conn.cursor()


def is_date_rfc3339_older_than_hours(date_str, hours):
    date = datetime.strptime(date_str, "%Y-%m-%dT%H:%M:%S%z")
    return datetime.now(date.tzinfo) - date > timedelta(hours=hours)


def was_checked_this_month(cursor: Cursor, proxy: str):
    cursor.execute(
        "SELECT COUNT(*) FROM proxies WHERE proxy = ? AND strftime('%Y-%m', last_check) = strftime('%Y-%m', 'now')",
        (proxy,),
    )
    return cursor.fetchone()[0] > 0


def was_checked_this_week(cursor: Cursor, proxy: str):
    start_of_week = (
        datetime.now() - timedelta(days=datetime.now().weekday())
    ).strftime("%Y-%m-%d")
    cursor.execute(
        "SELECT COUNT(*) FROM proxies WHERE proxy = ? AND last_check >= ?",
        (proxy, start_of_week),
    )
    return cursor.fetchone()[0] > 0


# Step 1: Identify and process duplicates based on IP address in batches
batch_size = 1000  # Adjust batch size as needed
start = 0
duplicate_ids = []
max_execution_time = 60  # Define maximum execution time
perform_delete = True


def fetch_proxies_same_ip(
    status: List[str] = ["dead", "port-closed", "untested"]
) -> Dict[str, List[str]]:
    """
    Fetch proxies that share the same IP but have different ports and match any of the given statuses.

    Args:
        status (List[str]): List of statuses to filter proxies. Defaults to ["dead", "port-closed", "untested"].

    Returns:
        Dict[str, List[str]]: A dictionary where keys are IP addresses and values are lists of proxies with different ports.
    """
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
    cursor.execute(query)

    # Fetch the results
    proxies: List[Dict[str, Union[str, None]]] = cursor.fetchall()

    # Process the results
    result: Dict[str, List[str]] = {}
    for row in proxies:
        proxy = row[0]
        ip = proxy.split(":")[0]
        if ip not in result:
            result[ip] = []
        result[ip].append(proxy)

    return result


def filter_duplicates_ips(max: int = 10, callback: Optional[Callable] = None):
    """
    Filter duplicated IPs by port open checks using multithreading.
    """
    duplicates_ips = fetch_proxies_same_ip()

    def process_ip(ip, ip_proxies):
        db = ProxyDB(get_relative_path("src/database.sqlite"), True)
        conn = db.db.conn
        cursor = conn.cursor()
        print(f"{ip} has {len(ip_proxies)} duplicates")

        if len(ip_proxies) > 1:
            # Fetch all rows matching the IP address (excluding active and port-open proxies)
            cursor.execute(
                """
                SELECT rowid, * FROM proxies
                WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) = ?
                AND status != 'active' AND status != 'port-open'
                ORDER BY RANDOM() LIMIT 0, 49999;
                """,
                (ip,),
            )
            ip_rows = cursor.fetchall()

            if len(ip_rows) > 1:
                keep_row = ip_rows[0]
                random.shuffle(ip_rows)

                for row in ip_rows:
                    proxy = row["proxy"]
                    if is_port_open(proxy):
                        print(f"{proxy} {green('port open')}")
                        # keep open port
                        keep_row = row
                        # set status to port-open
                        last_check = datetime.now().strftime("%Y-%m-%dT%H:%M:%S%z")
                        cursor.execute(
                            "UPDATE proxies SET last_check = ?, status = ? WHERE proxy = ?",
                            (last_check, "port-open", proxy),
                        )
                        conn.commit()
                    else:
                        print(f"{proxy} {red('port closed')}")
                        if keep_row["proxy"] != proxy:
                            cursor.execute(
                                "DELETE FROM proxies WHERE proxy = ?", (proxy,)
                            )
                            conn.commit()

        db.close()

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
                print(f"Error processing IP: {e}")

    if callable(callback):
        callback()


def worker_check_open_ports(item: Dict[str, str]):
    try:
        db = ProxyDB(get_relative_path("src/database.sqlite"), True)
        conn = db.db.conn
        cursor = conn.cursor()

        test = real_check(
            item["proxy"], "https://www.axis.co.id/bantuan", "pusat layanan"
        )
        if not test["result"]:
            test = real_check(item["proxy"], "https://www.example.com/", "example")

        if not test["result"]:
            test = real_check(item["proxy"], "http://azenv.net/", "AZ Environment")

        if not test["result"]:
            test = real_check(item["proxy"], "http://httpforever.com/", "HTTP Forever")

        last_check = datetime.now().strftime("%Y-%m-%dT%H:%M:%S%z")
        if test["result"]:
            cursor.execute(
                "UPDATE proxies SET last_check = ?, status = ? WHERE proxy = ?",
                (last_check, "active", item["proxy"]),
            )
            conn.commit()
        else:
            # cursor.execute("DELETE FROM proxies WHERE proxy = ?", (item["proxy"],))
            cursor.execute(
                "UPDATE proxies SET last_check = ?, status = ? WHERE proxy = ?",
                (last_check, "dead", item["proxy"]),
            )
            conn.commit()

    except sqlite3.Error as e:
        print(f"SQLite error occurred: {e}")
        # Handle the error as per your application's requirements
        # Example: Log the error, rollback transactions, etc.

    except Exception as e:
        print(f"Error occurred: {e}")
        # Handle other exceptions if needed

    finally:
        if "db" in locals():
            db.close()


def fetch_open_ports(max: int = 10) -> List[Dict[str, Union[str, None]]]:
    global cursor
    # Execute your query using the global cursor
    cursor.execute("SELECT proxy FROM proxies WHERE status = 'port-open'")
    proxies = cursor.fetchall()

    # Fetch column names
    column_names = [description[0] for description in cursor.description]

    # Convert the result into a list of dictionaries
    proxies_dict: List[Dict[str, Union[str, None]]] = [
        dict(zip(column_names, proxy)) for proxy in proxies
    ][:max]

    return proxies_dict


def check_open_ports(max: int = 10, callback: Optional[Callable] = None):
    """
    check proxy WHERE status = 'port-open'
    """
    proxies_dict = fetch_open_ports(max)
    if len(proxies_dict) > 0:
        Parallel(n_jobs=10)(
            delayed(worker_check_open_ports)(item) for item in proxies_dict
        )
    if callable(callback):
        callback()


def remove_duplicates_dead_proxies(max: int = 10, callback: Optional[Callable] = None):
    duplicates_ips: Dict[str, List[str]] = fetch_proxies_same_ip(
        ["dead", "port-closed"]
    )

    def process_ip(ip: str, ip_proxies: List[str]):
        print(f"Processing ip: {ip}")
        db = ProxyDB(get_relative_path("src/database.sqlite"), True)
        # keep first row (dont delete first row)
        ip_proxies_excluding_first = ip_proxies[1:]
        for proxy in ip_proxies_excluding_first:
            db.remove(proxy)
            print(f"{proxy} {red('removed')}")
        db.close()

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
                print(f"Error processing IP: {e}")
    if callable(callback):
        callback()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Proxy Tool")
    parser.add_argument("--max", type=int, help="Maximum number of proxies to check")
    args = parser.parse_args()
    max = 1
    if args.max:
        max = args.max

    filter_duplicates_ips(
        max, lambda: check_open_ports(max, lambda: remove_duplicates_dead_proxies(max))
    )

    # Close connection
    db.close()
