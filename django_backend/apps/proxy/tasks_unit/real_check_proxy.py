import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

import random
import re
import threading
import traceback
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import timedelta
from typing import List, Optional
from urllib.parse import urlparse

import requests
from bs4 import BeautifulSoup
from django.conf import settings
from django.utils import timezone
from proxy_hunter import decompress_requests_response, extract_proxies
from proxy_hunter.utils import check_raw_headers_keywords, is_valid_proxy

from django_backend.apps.proxy.models import Proxy
from django_backend.apps.proxy.utils import (
    execute_select_query,
    execute_sql_query,
)
from src.func import (
    file_append_str,
    get_message_exception,
    get_relative_path,
    read_file,
    remove_string_and_move_to_file,
)
from src.func_console import green, log_file, red
from src.func_date import get_current_rfc3339_time, is_date_rfc3339_older_than
from src.func_platform import is_debug
from src.func_proxy import ProxyCheckResult, build_request, is_port_open, upload_proxy

result_log_file = get_relative_path("proxyChecker.txt")


def real_check_proxy(proxy: str, type: str) -> ProxyCheckResult:
    global result_log_file

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
                    "Accept-Encoding": "gzip, deflate, br",
                },
            )
            latency = response.elapsed.total_seconds() * 1000  # in milliseconds
            status_code = response.status_code
            body = decompress_requests_response(response)
            soup = BeautifulSoup(body, "html.parser")
            title = soup.title.string if soup.title else ""
            final_url = response.url
            pattern = r"^https?:\/\/(?:www\.gstatic\.com|gateway\.(zs\w+)\.[a-zA-Z]{2,})(?::\d+)?\/.*(?:origurl)="
            regex_private_gateway = re.compile(pattern, re.IGNORECASE)
            if status_code == 200:
                if regex_private_gateway.match(final_url):
                    result = False
                    error = f"Private proxy {final_url}"
                elif check_raw_headers_keywords(body):
                    result = False
                    error = f"Private proxy Raw headers (Azenv) found in body from {final_url}"
                else:
                    result = (
                        title_should_be.lower() in title.lower() if title else False
                    )
            else:
                result = False
                error = f"Status code: {status_code}. Title: {title}."
        except Exception as e:
            error = get_message_exception(e)
            # print(f"testing {type}://{proxy} to {url}: {red('failed')} {error}")
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

    test = ProxyCheckResult(None, 0, "", None, False, None, proxy, None)
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


def get_proxies_query(
    status: List[str] = ["dead", "port-closed", "untested"],
    limit: Optional[int] = None,
):
    # Create a condition string from the status list
    condition = " OR ".join([f"status = '{s}'" for s in status])

    # Define the query to find proxies with specific status
    if "active" in status or "untested" in status or "port-open" in status:
        query = f"""
        SELECT *
        FROM proxies
        WHERE {condition}
        ORDER BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1), RANDOM()
        """
    else:
        query = f"""
        SELECT *
        FROM proxies
        WHERE {condition} OR status IS NULL
        ORDER BY SUBSTR(proxy, 1, INSTR(proxy, ':') - 1), RANDOM()
        """

    # Add LIMIT clause if limit is provided
    if limit is not None:
        query += f" LIMIT {limit}"
    return execute_select_query(query)


def get_proxies(
    status: List[str] = ["dead", "port-closed", "untested"], limit: Optional[int] = 100
):
    result = get_proxies_query(status, limit)

    # Create a set of unique proxies based on a unique key (e.g., 'proxy_id')
    unique_proxies = {proxy["proxy"]: proxy for proxy in result}.values()

    # log_file(
    #     result_log_file,
    #     f"get_proxies status={' OR '.join(status)} got {len(unique_proxies)} proxies",
    # )

    return list(unique_proxies)


def real_check_proxy_async(proxy_data: Optional[str] = ""):
    global result_log_file
    if not proxy_data:
        proxy_data = ""
    proxies: List[Proxy] = []

    if len(proxy_data.strip()) < 11:
        # Filter by last_check more than 12 hours ago
        outdated = get_proxies(["active"], sys.maxsize)
        outdated = [
            item
            for item in outdated
            if is_date_rfc3339_older_than(item["last_check"], 12)
        ]
        if outdated:
            log_file(
                result_log_file,
                f"[CHECKER-PARALLEL] got {len(outdated)} outdated proxies",
            )
            proxy_data += str([obj.to_json() for obj in outdated.order_by("?")])
        else:
            items = get_proxies(["untested"], 30)
            if items:
                log_file(
                    result_log_file,
                    f"[CHECKER-PARALLEL] got {len(items)} untested proxies",
                )
            else:
                items.extend(get_proxies(["dead", "port-closed"], 30))
                log_file(
                    result_log_file,
                    f"[CHECKER-PARALLEL] got {len(items)} dead proxies",
                )
            for item in items:
                format = item["proxy"]
                if item["username"] and item["password"]:
                    format += f"@{item['username']}:{item['password']}"
                proxy_data += f"\n{format}\n"
    if len(proxy_data.strip()) < 11:
        php_results = [
            # read_file(get_relative_path("working.json")),
            read_file(get_relative_path("proxies.txt")),
        ]
        for php_result in php_results:
            proxy_data = f"{php_result} {proxy_data}"
        log_file(result_log_file, f"source proxy from reading {len(php_result)} files")
    if proxy_data:
        extract = extract_proxies(proxy_data)
        for item in extract:
            find, created = Proxy.objects.get_or_create(
                proxy=item.proxy,
                defaults={"password": item.password, "username": item.username},
            )
            if find:
                proxies.append(find[0])
    if proxies:
        # shuffle items
        random.shuffle(proxies)
    # iterate [n] proxies
    for proxy_obj in proxies[: settings.LIMIT_PROXIES_CHECK]:
        # reset indicator each item
        status = None
        working = False
        protocols = []
        https = False
        status = None
        latency = 0
        # validate proxy
        valid = is_valid_proxy(proxy_obj.proxy)
        if not valid:
            execute_sql_query("DELETE FROM proxies WHERE proxy = ?", (proxy_obj.proxy,))
            log_file(
                result_log_file,
                f"[CHECKER-PARALLEL] {proxy_obj.proxy} invalid [DELETED]",
            )
            continue
        # check if proxy exist in database model
        if not Proxy.objects.filter(proxy=proxy_obj.proxy):
            Proxy.objects.create(
                proxy=proxy_obj.proxy,
                username=proxy_obj.username,
                password=proxy_obj.password,
            )
        if not is_port_open(proxy_obj.proxy):
            log = f"[CHECKER-PARALLEL] {proxy_obj.proxy} {red('port closed')}"
            log_file(result_log_file, log)
            status = "port-closed"
        else:
            # Define a function to handle check_proxy with the correct arguments
            def handle_check(protocol):
                return real_check_proxy(proxy_obj.proxy, protocol)

            try:
                # Create a ThreadPoolExecutor
                with ThreadPoolExecutor(
                    max_workers=settings.WORKER_THREADS
                ) as executor:
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
                                log = f"[CHECKER-PARALLEL] {protocol.lower()}://{proxy_obj.proxy} {green('working')}. Title: {check.additional['title']}. Url: {check.url}"
                                protocols.append(protocol.lower())
                                log_file(result_log_file, log)
                                working = True
                                https = check.https
                                if int(check.latency) > latency:
                                    latency = int(check.latency)
                            else:
                                log = f"[CHECKER-PARALLEL] {protocol.lower()}://{proxy_obj.proxy} dead -> {check.error}"
                                log_file(result_log_file, log)
                                working = False
                        except Exception as e:
                            log_file(result_log_file, f"Exception in future: {e}")

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
                log_file(
                    result_log_file, f"[CHECKER-PARALLEL] failed create thread {e}"
                )
                if "cannot schedule new futures" not in str(e).lower():
                    traceback.print_exc()

        if status is not None:
            last_check = get_current_rfc3339_time()
            # log_file(result_log_file, f"{proxyClass.proxy} last check {last_check}")
            data = {
                "status": status,
                "type": "-".join(protocols).lower() if len(protocols) > 0 else None,
                "last_check": last_check,
                "https": "true" if https else "false",
                "latency": latency,
            }
            model, created = Proxy.objects.get_or_create(
                proxy=proxy_obj.proxy, defaults=data
            )
            try:
                exec = execute_sql_query(model.to_insert_or_ignore_sql())
                if "error" in exec:
                    for err in exec["error"]:
                        log_file(result_log_file, f"[CHECKER-PARALLEL] {err}")
                exec = execute_sql_query(model.to_update_sql())
                if "error" in exec:
                    for err in exec["error"]:
                        log_file(result_log_file, f"[CHECKER-PARALLEL] {err}")
            except Exception as e:
                log_file(result_log_file, f"{proxy_obj.proxy} failed to update: {e}")
        remove_string_and_move_to_file(
            get_relative_path("proxies.txt"),
            get_relative_path("dead.txt"),
            proxy_obj.proxy,
        )

    file_append_str(result_log_file, f"\n{len(proxies)} proxies checked done.\n")


def real_check_proxy_async_in_thread(proxy):
    thread = threading.Thread(target=real_check_proxy_async, args=(proxy,))
    # thread = threading.Thread(target=get_proxies, args=(["untested"],))
    thread.start()
    return thread
