# django_backend/apps/proxy/tasks.py

import atexit
import os
import random
import string
import sys
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime
from typing import Any, List, Optional
from src.func_useragent import random_windows_ua
import requests

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

from bs4 import BeautifulSoup

from src.func import (
    file_append_str,
    file_remove_empty_lines,
    get_message_exception,
    get_relative_path,
    read_file,
    remove_string_and_move_to_file,
    truncate_file_content,
    write_file,
)
from src.func_console import green, red
from src.func_proxy import (
    ProxyCheckResult,
    build_request,
    is_port_open,
    log_proxy,
    upload_proxy,
)
from src.ProxyDB import ProxyDB
from .models import Proxy
from .utils import get_geo_ip2
from data.webgl import random_webgl_data


def fetch_details(model: Proxy):
    save = False
    if not model.timezone or not model.country or not model.lang:
        detail = get_geo_ip2(model.proxy, model.username, model.password)
        if detail:
            model.city = detail.city
            model.country = detail.country_name
            model.timezone = detail.timezone
            model.latitude = detail.latitude
            model.longitude = detail.longitude
            model.region = detail.region_name
            if not detail.lang:
                detail.lang = "en"
            model.lang = detail.lang
            save = True
        else:
            print(f"Failed to get geo IP for {model.proxy}")
    if not model.webgl_renderer or not model.webgl_vendor or not model.browser_vendor:
        webgl_data = random_webgl_data()
        model.webgl_renderer = webgl_data.webgl_renderer
        model.webgl_vendor = webgl_data.webgl_vendor
        model.browser_vendor = webgl_data.browser_vendor
        save = True
    if not model.useragent:
        model.useragent = random_windows_ua()
        save = True
    if save:
        model.save()


def fetch_details_list(proxies: List[Proxy]):
    try:
        with ThreadPoolExecutor(max_workers=10) as executor:
            futures = []
            for item in proxies:
                futures.append(executor.submit(fetch_details, item))
            # Ensure all threads complete before returning
            for future in futures:
                future.result()
    except RuntimeError as e:
        print(f"RuntimeError during fetch_details_list execution: {e}")


def fetch_details_in_thread(proxies):
    thread = threading.Thread(target=fetch_details_list, args=(proxies,))
    thread.daemon = True  # Allow thread to be killed when main program exits
    thread.start()


def get_runner_id(identifier: Any):
    id = str(identifier)
    return get_relative_path(f"tmp/runner/{id}.lock")


def real_check_proxy(proxy: str, type: str):
    result = False
    status_code = 0
    response = None
    latency = -1
    error = ""
    # format = f"{type}://{proxy}"
    try:
        response: requests.Response = build_request(
            proxy=proxy,
            proxy_type=type,
            endpoint="https://www.djangoproject.com/",
            headers={"Connection": "Keep-Alive", "Accept-Language": "en-US"},
        )
        latency = response.elapsed.total_seconds() * 1000  # in milliseconds
        status_code = response.status_code
        if response.status_code == 200:
            soup = BeautifulSoup(response.text, "html.parser")
            title = soup.title.string if soup.title else ""
            result = "django" in title.lower() if title else False
            # print(f"{green(format)} working")
        # else:
        #     print(f"{red(format)} dead (status {status_code})")
    except Exception as e:
        # print(f"fail check {red(format)}: {e}")
        error = get_message_exception(e)
        pass
    return ProxyCheckResult(
        result,
        latency=latency,
        error=error,
        private=False,
        status=status_code,
        response=response,
        proxy=proxy,
        type=type,
    )


def real_check_proxy_async(proxy_data: Optional[str] = None):
    db = ProxyDB(get_relative_path("tmp/database.sqlite"))
    logfile = get_relative_path("proxyChecker.txt")
    truncate_file_content(logfile)
    status = None
    working = False
    protocols = []
    if not proxy_data:
        proxy_data = str(db.get_untested_proxies(30))
    if len(proxy_data.strip()) < 11:
        php_results = [
            read_file(get_relative_path("working.json")),
            read_file(get_relative_path("proxies.txt")),
            read_file(get_relative_path("dead.txt")),
        ]
        for php_result in php_results:
            proxy_data = f"{php_result} {proxy_data}"
    proxies = db.extract_proxies(proxy_data, False)
    for proxyClass in proxies:
        if not is_port_open(proxyClass.proxy):
            log_proxy(f"{proxyClass.proxy} {red('port closed')}")
            status = "port-closed"
        else:
            # Define a function to handle check_proxy with the correct arguments
            def handle_check(protocol):
                return real_check_proxy(proxyClass.proxy, protocol)

            # Create a ThreadPoolExecutor
            with ThreadPoolExecutor(max_workers=3) as executor:
                # Submit the tasks
                checks = [
                    executor.submit(handle_check, "http"),
                    executor.submit(handle_check, "socks4"),
                    executor.submit(handle_check, "socks5"),
                ]

                # Iterate through the completed tasks
                for i, future in enumerate(as_completed(checks)):
                    protocol = ["HTTP", "SOCKS4", "SOCKS5"][i]
                    check = future.result()

                    if check.result:
                        log = f"> {proxyClass.proxy} ✓ {protocol}"
                        protocols.append(protocol.lower())
                        file_append_str(logfile, log)
                        print(green(log))
                        working = True
                    else:
                        log = f"> {proxyClass.proxy} 🗙 {protocol}"
                        file_append_str(logfile, f"{log} -> {check.error}")
                        print(f"{red(log)} -> {check.error}")
                        working = False

                if not working:
                    status = "dead"
                else:
                    status = "active"
                    upload_proxy(proxyClass)

        if db is not None and status is not None:
            data = {"status": status}
            if len(protocols) > 0:
                data["type"] = "-".join(protocols).upper()
            try:
                db.update_data(proxyClass.proxy, data)
            except Exception as e:
                print(f"{proxyClass.proxy} fail update {e}")

        remove_string_and_move_to_file(
            get_relative_path("proxies.txt"),
            get_relative_path("dead.txt"),
            proxyClass.proxy,
        )
    file_remove_empty_lines(logfile)


def run_check_proxy_async_in_thread(proxy):
    thread = threading.Thread(target=real_check_proxy_async, args=(proxy,))
    thread.start()
    return thread


def debug_task():
    date_time = datetime.now()
    allowed_chars = string.ascii_letters + string.punctuation
    unique = "".join(random.choice(allowed_chars) for x in range(100))
    log = f"Debug task executed. ({date_time} - {unique})"
    print(log)
    write_file(get_relative_path("tmp/runner/x.txt"), log)
    return {"result": "Task completed successfully", "message": log}
