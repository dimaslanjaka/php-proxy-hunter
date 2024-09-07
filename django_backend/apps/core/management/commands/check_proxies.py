import json
import os
import sys
import threading
import time

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

import random
from multiprocessing.pool import ThreadPool as Pool
from typing import Dict, List
from django_backend.apps.proxy.tasks_unit.filter_ports_proxy import (
    check_open_ports,
    filter_duplicates_ips,
)
from django_backend.apps.proxy.tasks_unit.geolocation import fetch_geo_ip
from django_backend.apps.proxy.tasks_unit.real_check_proxy import real_check_proxy_async
from bs4 import BeautifulSoup
from django.core.management.base import BaseCommand
from joblib import Parallel, delayed
from django.apps import apps
from src.func import (
    get_relative_path,
)
from proxy_hunter import file_append_str, sanitize_filename, truncate_file_content
from src.func_console import green, red
from proxy_hunter import check_proxy


def real_check(proxy: str, url: str, title_should_be: str):
    protocols: List[str] = []
    output_file = get_relative_path(f"tmp/logs/{sanitize_filename(proxy)}.txt")
    truncate_file_content(output_file)
    response_title = ""

    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
        "Accept-Language": "en-US,en;q=0.9",
    }

    checks = {
        "socks4": check_proxy(proxy, "socks4", url, headers),
        "http": check_proxy(proxy, "http", url, headers),
        "socks5": check_proxy(proxy, "socks5", url, headers),
    }

    for proxy_type, check in checks.items():
        log = f"{check.type}://{check.proxy}\n"
        log += f"RESULT: {'true' if check.result else 'false'}\n"
        if not check.result and check.error:
            log += f"ERROR: {check.error.strip()}\n"
        if check.response:
            log += "RESPONSE HEADERS:\n"
            for key, value in check.response.headers.items():
                log += f"  {key}: {value}\n"
            if check.response.text:
                soup = BeautifulSoup(check.response.text, "html.parser")
                response_title = soup.title.string.strip() if soup.title else ""
                log += f"TITLE: {response_title}\n"
                if title_should_be.lower() in response_title.lower():
                    protocols.append(proxy_type.lower())
            file_append_str(output_file, log)

    if os.path.exists(output_file):
        print(f"logs written {output_file}")

    result = {
        "result": False,
        "url": url,
        "https": url.startswith("https://"),
        "proxy": proxy,
        "protocols": protocols,
    }
    if protocols:
        print(f"{proxy} {green('working')} \t {url} \t ({response_title})")
        result["result"] = True
    else:
        print(f"{proxy} {red('dead')} \t {url} \t ({response_title})")
        result["result"] = False
    return result


processed_proxies = set()
lock = threading.Lock()


def worker(item: Dict[str, str]):
    from django_backend.apps.proxy.utils import execute_sql_query

    proxy = item["proxy"]

    with lock:
        if proxy in processed_proxies:
            print(f"Skipping already processed proxy: {proxy}")
            return
        processed_proxies.add(proxy)

    print(f"Processing proxy: {proxy}")

    urls = [
        ("https://www.axis.co.id/bantuan", "pusat layanan"),
        ("https://www.example.com/", "example"),
        ("http://azenv.net/", "AZ Environment"),
        ("http://httpforever.com/", "HTTP Forever"),
    ]

    try:
        for url, title in urls:
            test = real_check(proxy, url, title)
            if test["result"]:
                https = "true" if test["https"] else "false"
                protocols = "-".join(test["protocols"]).lower()
                execute_sql_query(
                    f"UPDATE proxies SET status = 'active', type = '{protocols}', https = '{https}', last_check = strftime('%Y-%m-%dT%H:%M:%SZ', 'now') WHERE proxy = '{proxy}';"
                )
                fetch_geo_ip(proxy)
                break
        else:
            execute_sql_query(
                f"UPDATE proxies SET status = 'dead' WHERE proxy = '{proxy}';"
            )
    except Exception as e:
        print(f"Error processing item {item}: {str(e)}")


def using_joblib(proxies: List[Dict[str, str]], pool_size: int = 5):
    effective_pool_size = min(len(proxies), pool_size)
    print(
        f"Checking {len(proxies)} proxies using joblib with {effective_pool_size} threads"
    )
    Parallel(n_jobs=effective_pool_size)(delayed(worker)(item) for item in proxies)


def using_pool(proxies: List[Dict[str, str]], pool_size: int = 5):
    effective_pool_size = min(len(proxies), pool_size)
    print(
        f"Checking {len(proxies)} proxies using multiprocessing.pool.ThreadPool with {effective_pool_size} threads"
    )
    pool = Pool(effective_pool_size)
    for item in proxies:
        if not item:
            continue
        pool.apply_async(worker, (item,))
    pool.close()
    pool.join()


class Command(BaseCommand):
    help = "Check the status of proxies and update the database."

    def add_arguments(self, parser):
        parser.add_argument(
            "--max",
            type=int,
            default=100,
            help="Maximum number of proxies to process (default: 100)",
        )

    def handle(self, *args, **options):
        max_proxies = options["max"]
        self.wait_for_app_ready("django_backend.apps.proxy")

        proxies: List[Dict[str, str | int | float | None]] = []

        untested_proxies = self.select_proxies(
            f"SELECT * FROM proxies WHERE status = 'untested' ORDER BY RANDOM() LIMIT {max_proxies}"
        )
        if untested_proxies:
            proxies.extend(untested_proxies)

        active_proxies = self.select_proxies(
            f"""
            SELECT *
            FROM proxies
            WHERE status = 'active'
            AND datetime(last_check) < datetime('now', '-4 hours')
            ORDER BY RANDOM()
            LIMIT {max_proxies};
            """
        )
        if active_proxies:
            proxies.extend(active_proxies)

        undefined_proxies = self.select_proxies(
            f"SELECT * FROM proxies WHERE status IS NULL ORDER BY RANDOM() LIMIT {max_proxies}"
        )
        if undefined_proxies:
            proxies.extend(undefined_proxies)

        proxy = json.dumps(proxies)

        # Start threads
        threads: List[threading.Thread] = []
        for item in active_proxies:
            if not item["timezone"]:
                # fetch geolocation on active proxy
                threads.append(
                    threading.Thread(target=fetch_geo_ip, args=(item["proxy"],))
                )
        threads.append(
            threading.Thread(target=filter_duplicates_ips, args=(max_proxies,))
        )
        threads.append(threading.Thread(target=check_open_ports, args=(max_proxies,)))
        threads.append(threading.Thread(target=real_check_proxy_async, args=(proxy,)))

        # Start all threads
        for thread in threads:
            thread.start()

        # Wait for all threads to complete
        for thread in threads:
            thread.join()

    def select_proxies(self, sql: str):
        from django_backend.apps.proxy.utils import execute_select_query

        proxies = execute_select_query(sql.strip())
        random.shuffle(proxies)
        unique_data = list({entry["proxy"]: entry for entry in proxies}.values())
        return unique_data

    def wait_for_app_ready(self, app_name):
        max_retries = 10
        interval = 1  # seconds

        for _attempt in range(max_retries):
            try:
                # Check if the app is ready
                if apps.is_installed(app_name):
                    self.stdout.write(f"{app_name} is ready.")
                    return
            except Exception as e:
                self.stderr.write(f"Error checking if app is ready: {e}")

            self.stdout.write(f"{app_name} not ready yet, retrying...")
            time.sleep(interval)

        self.stderr.write(f"{app_name} did not become ready in time.")
        raise RuntimeError(f"{app_name} did not become ready.")
