import os
import sys
import threading
import time

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

import random
from typing import Dict, List

from django.apps import apps
from django.core.management.base import BaseCommand
from filelock import FileLock
from filelock import Timeout as FileLockTimeout

from django_backend.apps.proxy.tasks_unit.filter_ports_proxy import (
    check_open_ports,
    filter_duplicates_ips,
)
from django_backend.apps.proxy.tasks_unit.geolocation import fetch_geo_ip
from src.func import get_relative_path

processed_proxies = set()
lock = threading.Lock()


class Command(BaseCommand):
    help = "Check the status of proxies and update the database."
    lockfile = get_relative_path("tmp/django_filter_dups.lock")

    def add_arguments(self, parser):
        parser.add_argument(
            "--max",
            type=int,
            default=100,
            help="Maximum number of proxies to process (default: 100)",
        )

    def handle(self, *args, **options):
        lock = FileLock(self.lockfile, timeout=0)
        try:
            # Try acquiring the lock
            with lock:
                self.stdout.write("Lock acquired. Running the command...")
                self.run_command(**options)
        except FileLockTimeout:
            self.stderr.write("Another instance is already running. Exiting.")

    def run_command(self, **options):
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
