from datetime import datetime, timedelta
import random
import sqlite3
import time
from src.ProxyDB import ProxyDB
from src.func import get_relative_path, file_append_str
from src.func_proxy import is_port_open, is_valid_ip, is_valid_ip_port
from sqlite3 import Cursor
import os, sys

db = ProxyDB(get_relative_path('src/database.sqlite'), True)
conn = db.db.conn
cursor = conn.cursor()


def is_date_rfc3339_older_than_hours(date_str, hours):
    date = datetime.strptime(date_str, "%Y-%m-%dT%H:%M:%S%z")
    return datetime.now(date.tzinfo) - date > timedelta(hours=hours)


def was_checked_this_month(cursor: Cursor, proxy: str):
    cursor.execute("SELECT COUNT(*) FROM proxies WHERE proxy = ? AND strftime('%Y-%m', last_check) = strftime('%Y-%m', 'now')", (proxy,))
    return cursor.fetchone()[0] > 0


def was_checked_this_week(cursor: Cursor, proxy: str):
    start_of_week = (datetime.now() - timedelta(days=datetime.now().weekday())).strftime('%Y-%m-%d')
    cursor.execute("SELECT COUNT(*) FROM proxies WHERE proxy = ? AND last_check >= ?", (proxy, start_of_week))
    return cursor.fetchone()[0] > 0


# Step 1: Identify and process duplicates based on IP address in batches
batch_size = 1000  # Adjust batch size as needed
start = 0
duplicate_ids = []
max_execution_time = 60  # Define maximum execution time
perform_delete = True


def fetch_proxies_same_ip():
    # Query to fetch proxies with the same IP but different ports
    query = """
    SELECT proxy
    FROM proxies
    WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) IN (
        SELECT SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
        FROM proxies
        WHERE status != 'active'
        AND status != 'port-open'
        GROUP BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
        HAVING COUNT(*) > 1
    )
    ORDER BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1)
    """
    cursor.execute(query)

    # Fetch the results
    proxies = cursor.fetchall()

    # Process the results
    result = {}
    for row in proxies:
        proxy = row[0]
        ip = proxy.split(':')[0]
        if ip not in result:
            result[ip] = []
        result[ip].append(proxy)

    return result


# TODO: filter duplicated ips
duplicates_ips = fetch_proxies_same_ip()
for ip in duplicates_ips:
    # Loop each ip

    # Re-count the same IP
    cursor.execute("""
    SELECT COUNT(*) as count
    FROM proxies
    WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) = ?
    AND status != 'active'
    AND status != 'port-open'
    ORDER BY RANDOM() LIMIT 0, 49999;
    """, (ip,))
    count = cursor.fetchone()[0]

    if count < 3:
        continue
    else:
        # Fetch all rows matching the IP address (including port)
        # Exclude active proxies
        cursor.execute("""
        SELECT rowid, * FROM proxies
        WHERE SUBSTR(proxy, 1, INSTR(proxy, ':') - 1) = ?
        AND status != 'active' AND status != 'port-open'
        ORDER BY RANDOM() LIMIT 0, 49999;
        """, (ip,))
        ip_rows = cursor.fetchall()

        print(f"{ip} has {count} duplicates, matched {len(ip_rows)}")

        if len(ip_rows) > 1:
            keep_row = ip_rows[0]
            random.shuffle(ip_rows)

            for row in ip_rows:
                proxy = row['proxy']
                if is_port_open(proxy):
                    print(f"{proxy} port open")
                    # keep open port
                    keep_row = row
                    # set status to port-open
                    last_check = datetime.now().strftime("%Y-%m-%dT%H:%M:%S%z")  # Assign date to a variable
                    cursor.execute("UPDATE proxies SET last_check = ?, status = ? WHERE proxy = ?", (last_check, 'port-open', proxy))
                    conn.commit()
                elif keep_row['proxy'] != proxy:
                    print(f"{proxy} port closed")
                    cursor.execute("DELETE FROM proxies WHERE proxy = ?", (proxy,))
                    conn.commit()

# TODO: check proxy status = 'port-open'

# Close connection
conn.close()
