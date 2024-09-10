import json
import os
import random
import sys
import threading
import time
from typing import Dict, List

from django.apps import apps
from django.core.management.base import BaseCommand
from proxy_hunter import log, proxy_hunter2, extract_ips, gen_ports, iterate_gen_ports

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

from django_backend.apps.proxy.utils import execute_select_query

# python manage.py proxy_hunter --data="custom IP/Proxy" --debug=False --force=False --max=100


class Command(BaseCommand):
    help = "Check if a proxy is active and its port is open"

    def add_arguments(self, parser):
        parser.add_argument(
            "--max",
            type=int,
            default=100,
            help="Maximum number of proxies to process (default: 100)",
        )
        parser.add_argument("--debug", type=bool, default=False, help="Show debug")
        parser.add_argument(
            "--force", type=bool, default=False, help="Force regenerate ip port pairs"
        )
        parser.add_argument(
            "--data", type=str, default="", help="Custom ip/proxy string"
        )

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

    def handle(self, *args, **options):
        max_proxies: int = options.get("max", 100)
        debug: bool = options.get("debug", False)
        force: bool = options.get("force", False)
        proxies: str = options.get("data", "")
        self.wait_for_app_ready("django_backend.apps.proxy")

        if not proxies:
            proxies = str(
                [
                    item.get("proxy")
                    for item in self.select_proxies(
                        f"SELECT * FROM proxies ORDER BY RANDOM() LIMIT {max_proxies}"
                    )
                ]
            )

        ips = extract_ips(proxies)[:max_proxies]  # extract ip and limit result
        gen_ports(str(ips), force, debug)
        iterate_gen_ports(str(ips), self.callback, debug)

    def callback(self, proxy, is_port_open, is_proxy_active):
        log(
            f"{proxy}: {'port open' if is_port_open else 'port closed'}, "
            f"{'proxy active' if is_proxy_active else 'proxy dead'}",
            end="\r" if not is_port_open else "\n",
        )

    def select_proxies(self, sql: str):
        proxies = execute_select_query(sql.strip())
        random.shuffle(proxies)
        unique_data = list({entry["proxy"]: entry for entry in proxies}.values())
        return unique_data
