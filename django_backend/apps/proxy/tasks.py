import os
import random
import sys
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
import traceback
from typing import Any, List, Optional

import requests

from src.func_platform import is_debug

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))
from bs4 import BeautifulSoup
from proxy_hunter import extract_proxies
from django.db.models import Q
from data.webgl import random_webgl_data
from src.func import (
    debug_exception,
    file_append_str,
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

logfile = get_relative_path("tmp/logs/proxyChecker.txt")


def fetch_geo_ip(proxy: str):
    save = False
    queryset = Proxy.objects.filter(proxy=proxy)
    if not queryset:
        return
    model = queryset[0]

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
        try:
            model.save()
        except Exception as e:
            print(f"fetch_geo_ip fail update proxy model {model.to_json()}. {e}")


def fetch_geo_ip_list(proxies: List[Proxy]):
    try:
        with ThreadPoolExecutor(max_workers=10) as executor:
            futures = []
            for item in proxies:
                futures.append(executor.submit(fetch_geo_ip, item.proxy))
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
        title = ""
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
            soup = BeautifulSoup(response.text, "html.parser")
            title = soup.title.string if soup.title else ""
            if status_code == 200:
                result = title_should_be.lower() in title.lower() if title else False
            else:
                result = False
                error = f"Status code: {status_code}. Title: {title}."
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
            additional={"title": title},
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


def real_check_proxy_async(proxy_data: Optional[str] = ""):
    if not proxy_data:
        proxy_data = ""
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
    if len(proxy_data.strip()) < 11:
        queryset = Proxy.objects.filter(
            (
                Q(type__isnull=True) | Q(last_check__isnull=True) | Q(type="-")
            )  # `type` is None or '-'
            & ~Q(status="untested")  # `status` is not 'untested'
            & ~Q(status="dead")  # `status` is not 'dead'
            & ~Q(status="port-closed")  # `status` is not 'port-closed'
        )
        print(f"source proxy from Model: got {len(queryset)} proxies from type=None")
        if not queryset:
            queryset = Proxy.objects.filter(status="untested")[:30]
            print(
                f"source proxy from Model: got {len(queryset)} proxies from status=untested"
            )
        if not queryset:
            queryset = Proxy.objects.filter(Q(status__isnull=True) | Q(status="-"))
            print(
                f"source proxy from Model: got {len(queryset)} proxies from status=None"
            )
        if queryset:
            proxy_data += str([obj.to_json() for obj in queryset])
        # get 30 untested proxies
        if db is not None and len(proxy_data.strip()) < 11:
            try:
                proxy_data += str(db.get_untested_proxies(30))
                print(
                    f"source proxy from ProxyDB: got {len(queryset)} proxies from status=untested"
                )
            except Exception:
                pass
    if len(proxy_data.strip()) < 11:
        php_results = [
            read_file(get_relative_path("working.json")),
            read_file(get_relative_path("proxies.txt")),
        ]
        for php_result in php_results:
            proxy_data = f"{php_result} {proxy_data}"
        print(f"source proxy from reading {len(php_result)} files")
    if proxy_data:
        extract = []
        if db is not None:
            try:
                extract = db.extract_proxies(proxy_data)
            except Exception:
                pass
        if not extract:
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
    if proxies:
        # shuffle items
        random.shuffle(proxies)
    # iterate 30 proxies
    for proxy_obj in proxies[:30]:
        # reset status each item
        status = None
        # check if proxy exist in database model
        if not Proxy.objects.filter(proxy=proxy_obj.proxy):
            Proxy.objects.create(
                proxy=proxy_obj.proxy,
                username=proxy_obj.username,
                password=proxy_obj.password,
            )
        if not is_port_open(proxy_obj.proxy):
            log = f"> {proxy_obj.proxy} {red('port closed')}"
            file_append_str(logfile, log)
            print(log)
            status = "port-closed"
        else:
            # Define a function to handle check_proxy with the correct arguments
            def handle_check(protocol):
                return real_check_proxy(proxy_obj.proxy, protocol)

            try:
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
                                log = f"> {protocol.lower()}://{proxy_obj.proxy} working. Title: {check.additional['title']}. Url: {check.url}"
                                protocols.append(protocol.lower())
                                file_append_str(logfile, log)
                                print(green(log))
                                working = True
                                https = check.https
                            else:
                                log = f"> {protocol.lower()}://{proxy_obj.proxy} dead"
                                file_append_str(logfile, f"{log} -> {check.error}")
                                print(f"{red(log)} -> {check.error}")
                                working = False
                        except Exception as e:
                            print(f"Exception in future: {e}")

                    if not working:
                        status = "dead"
                    else:
                        status = "active"
                        if is_debug():
                            if proxy_obj.username and proxy_obj.password:
                                upload_proxy(
                                    f"{proxy_obj.proxy}@{proxy_obj.username}:{proxy_obj.password}"
                                )
                            else:
                                upload_proxy(proxy_obj.proxy)
                    # executor.shutdown(wait=True, cancel_futures=True)
            except Exception as e:
                print(f"real_check_proxy_async failed create thread {e}")
                if "cannot schedule new futures" not in str(e).lower():
                    traceback.print_exc()

        if status is not None:
            last_check = get_current_rfc3339_time()
            # print(f"{proxyClass.proxy} last check {last_check}")
            data = {
                "status": status,
                "type": "-".join(protocols).lower() if len(protocols) > 0 else None,
                "last_check": last_check,
                "https": "true" if https else "false",
            }
            try:
                if db is not None:
                    try:
                        # update data and avoid database locked
                        db.update_data(proxy_obj.proxy, data)
                    except Exception:
                        pass

                # Update Django database
                check_model, created = Proxy.objects.update_or_create(
                    proxy=proxy_obj.proxy,  # Field to match
                    defaults=data,  # Fields to update
                )
                if status == "active":
                    print(data)
                    print(check_model.to_json(), created)
            except Exception as e:
                print(f"{proxy_obj.proxy} failed to update: {e}")
        remove_string_and_move_to_file(
            get_relative_path("proxies.txt"),
            get_relative_path("dead.txt"),
            proxy_obj.proxy,
        )

    if db is not None:
        try:
            db.close()
        except Exception:
            pass
    file_append_str(logfile, f"\n{len(proxies)} proxies checked done.\n")


def real_check_proxy_async_in_thread(proxy):
    thread = threading.Thread(target=real_check_proxy_async, args=(proxy,))
    thread.start()
    return thread
