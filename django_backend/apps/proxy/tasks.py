import os
import random
import sys
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any, List, Optional

import requests

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))
from bs4 import BeautifulSoup
from proxy_hunter import extract_proxies

from data.webgl import random_webgl_data
from src.func import (
    file_append_str,
    file_remove_empty_lines,
    get_message_exception,
    get_relative_path,
    read_file,
    remove_string_and_move_to_file,
)
from src.func_console import green, red
from src.func_date import get_current_rfc3339_time
from src.func_proxy import (
    ProxyCheckResult,
    build_request,
    is_port_open,
    log_proxy,
    upload_proxy,
)
from src.func_useragent import random_windows_ua
from src.ProxyDB import ProxyDB

from .models import Proxy
from .utils import get_geo_ip2

logfile = get_relative_path("proxyChecker.txt")


def fetch_geo_ip(model: Proxy):
    save = False

    # Fetch geo IP details if necessary
    if model.timezone is None or model.country is None or model.lang is None:
        detail = get_geo_ip2(model.proxy, model.username, model.password)
        if detail:
            if model.city is None and detail.city:
                model.city = detail.city
                save = True
            if model.country is None and detail.country_name:
                model.country = detail.country_name
                save = True
            if model.timezone is None and detail.timezone:
                model.timezone = detail.timezone
                save = True
            if model.latitude is None and detail.latitude:
                model.latitude = detail.latitude
                save = True
            if model.longitude is None and detail.longitude:
                model.longitude = detail.longitude
                save = True
            if model.region is None and detail.region_name:
                model.region = detail.region_name
                save = True
            if model.lang is None:
                model.lang = detail.lang if detail.lang else "en"
                save = True
        else:
            print(f"Failed to get geo IP for {model.proxy}")

    # Fetch WebGL data if necessary
    if (
        model.webgl_renderer is None
        or model.webgl_vendor is None
        or model.browser_vendor is None
    ):
        webgl_data = random_webgl_data()
        if webgl_data:
            if model.webgl_renderer is None and webgl_data.webgl_renderer:
                model.webgl_renderer = webgl_data.webgl_renderer
                save = True
            if model.webgl_vendor is None and webgl_data.webgl_vendor:
                model.webgl_vendor = webgl_data.webgl_vendor
                save = True
            if model.browser_vendor is None and webgl_data.browser_vendor:
                model.browser_vendor = webgl_data.browser_vendor
                save = True

    # Fetch user agent if necessary
    if model.useragent is None:
        useragent = random_windows_ua()
        if useragent:
            model.useragent = useragent
            save = True

    if save:
        model.save()


def fetch_geo_ip_list(proxies: List[Proxy]):
    try:
        with ThreadPoolExecutor(max_workers=10) as executor:
            futures = []
            for item in proxies:
                futures.append(executor.submit(fetch_geo_ip, item))
            # Ensure all threads complete before returning
            for future in as_completed(futures):
                try:
                    future.result()
                except Exception as e:
                    print(f"Exception in future: {e}")
    except RuntimeError as e:
        print(f"RuntimeError during fetch_details_list execution: {e}")


def fetch_geo_ip_in_thread(proxies):
    thread = threading.Thread(target=fetch_geo_ip_list, args=(proxies,))
    thread.daemon = True  # Allow thread to be killed when main program exits
    thread.start()


def get_runner_id(identifier: Any):
    id = str(identifier)
    return get_relative_path(f"tmp/runner/{id}.lock")


def real_check_proxy(proxy: str, type: str):
    from urllib.parse import urlparse

    def is_https(url):
        parsed_url = urlparse(url)
        return parsed_url.scheme == "https"

    def inner_check(url: str, title_should_be: str):
        result = False
        status_code = 0
        response = None
        latency = -1
        error = ""
        try:
            response: requests.Response = build_request(
                proxy=proxy,
                proxy_type=type,
                endpoint=url,
                headers={
                    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36",
                    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Connection": "keep-alive",
                    "Upgrade-Insecure-Requests": "1",
                },
            )
            latency = response.elapsed.total_seconds() * 1000  # in milliseconds
            status_code = response.status_code
            if response.status_code == 200:
                soup = BeautifulSoup(response.text, "html.parser")
                title = soup.title.string if soup.title else ""
                result = title_should_be.lower() in title.lower() if title else False
        except Exception as e:
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
            https=is_https(url),
            url=url,
        )

    for url, name in [
        ("https://bing.com", "bing"),
        ("https://google.com/", "google"),
        ("https://github.com/", "github"),
        ("http://httpforever.com/", "http forever"),
        ("http://www.example.com/", "example domain"),
        ("http://www.example.net/", "example domain"),
    ]:
        test = inner_check(url, name)
        if test.result:
            break
    return test


def real_check_proxy_async(proxy_data: Optional[str] = None):
    db = None
    try:
        db = ProxyDB(get_relative_path("tmp/database.sqlite"), True)
    except Exception:
        pass
    status = None
    working = False
    protocols = []
    proxies: List[Proxy] = []
    https = False
    if not proxy_data:
        if db is not None:
            proxy_data = str(db.get_untested_proxies(30))
    if len(proxy_data.strip()) < 11:
        php_results = [
            read_file(get_relative_path("working.json")),
            read_file(get_relative_path("proxies.txt")),
        ]
        for php_result in php_results:
            proxy_data = f"{php_result} {proxy_data}"
    if proxy_data:
        if db is not None:
            extract = db.extract_proxies(proxy_data)
        else:
            extract = extract_proxies(proxy_data)
        for item in extract:
            find = Proxy.objects.filter(proxy=item.proxy)
            if not find:
                Proxy.objects.create(
                    proxy=item.proxy, username=item.username, password=item.password
                )
                find = Proxy.objects.filter(proxy=item.proxy)
            if find:
                proxies.append(find[0])
                random.shuffle(proxies)
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
                for future in as_completed(checks):
                    try:
                        check = future.result()
                        protocol = checks.index(
                            future
                        )  # Index the protocol list by order of completion
                        protocol = ["HTTP", "SOCKS4", "SOCKS5"][protocol]
                        if check.result:
                            log = f"> {protocol.lower()}://{proxyClass.proxy} working"
                            protocols.append(protocol.lower())
                            file_append_str(logfile, log)
                            print(green(log))
                            working = True
                            https = check.https
                        else:
                            log = f"> {protocol.lower()}://{proxyClass.proxy} dead"
                            file_append_str(logfile, f"{log} -> {check.error}")
                            print(f"{red(log)} -> {check.error}")
                            working = False
                    except Exception as e:
                        print(f"Exception in future: {e}")

                if not working:
                    status = "dead"
                else:
                    status = "active"
                    if proxyClass.username and proxyClass.password:
                        upload_proxy(
                            f"{proxyClass.proxy}@{proxyClass.username}:{proxyClass.password}"
                        )
                    else:
                        upload_proxy(proxyClass.proxy)

        if status is not None:
            last_check = get_current_rfc3339_time()
            # print(f"{proxyClass.proxy} last check {last_check}")
            data = {
                "status": status,
                "type": "-".join(protocols).upper() if len(protocols) > 0 else None,
                "last_check": last_check,
                "https": "true" if https else "false",
            }
            try:
                if db is not None:
                    db.update_data(proxyClass.proxy, data)

                # Update Django database
                check_model, created = Proxy.objects.update_or_create(
                    proxy=proxyClass.proxy,  # Field to match
                    defaults=data,  # Fields to update
                )
            except Exception as e:
                print(f"{proxyClass.proxy} failed to update: {e}")
        remove_string_and_move_to_file(
            get_relative_path("proxies.txt"),
            get_relative_path("dead.txt"),
            proxyClass.proxy,
        )
    file_remove_empty_lines(logfile)
    if db is not None:
        db.close()


def real_check_proxy_async_in_thread(proxy):
    thread = threading.Thread(target=real_check_proxy_async, args=(proxy,))
    thread.start()
    return thread
