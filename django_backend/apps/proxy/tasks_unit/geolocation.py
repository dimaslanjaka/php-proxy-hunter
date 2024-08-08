import json
import os
import sys

from proxy_hunter.utils import is_valid_ip

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

import threading
from concurrent.futures import Future, ThreadPoolExecutor, as_completed
from typing import Any, Dict, List, Optional, Set, Union

from django.conf import settings
from proxy_hunter import is_valid_proxy

from data.webgl import random_webgl_data
from django_backend.apps.proxy.models import Proxy
from django_backend.apps.proxy.utils import execute_select_query, execute_sql_query
from src.func import get_relative_path
from src.func_console import log_file
from src.func_useragent import random_windows_ua
from src.geoPlugin import get_geo_ip2

global_tasks: Set[Union[threading.Thread, Future]] = set()
result_log_file = get_relative_path("proxyChecker.txt")


def cleanup_threads():
    global global_tasks
    global_tasks = {
        task
        for task in global_tasks
        if (isinstance(task, threading.Thread) and task.is_alive())
        or (isinstance(task, Future) and not task.done())
    }


def fetch_geo_ip(data: Optional[str] = None):
    if not data:
        return

    result = {"error": None, "messages": None, "data": None}

    # validation
    valid_proxy = is_valid_proxy(data)
    if not valid_proxy:
        execute_sql_query("DELETE FROM proxies WHERE proxy = ?", (data,))
        print(f"{data} invalid - removed")

    valid_ip = is_valid_ip(data)
    if not valid_ip and not valid_proxy:
        result.update(
            {
                "error": True,
                "messages": "Invalid data. Only accept IP:PORT or IP only",
            }
        )

    save = False
    if valid_proxy:
        select = execute_select_query("SELECT * FROM proxies WHERE proxy = ?", (data,))
    elif valid_ip:
        select = execute_select_query(
            "SELECT * FROM proxies WHERE proxy LIKE ?", (f"%{data}%",)
        )

    model: Optional[Dict[str, Any]] = None
    if select:
        model = select[0]
    else:
        result["messages"] = f"fail get geolocation {data}"

    if model and model["proxy"]:
        # print("geolocation model", json.dumps(model, indent=2))
        detail = get_geo_ip2(model["proxy"], model["username"], model["password"])
        # print("geolocation detail", json.dumps(detail.to_dict(), indent=2))
        if detail:
            model["city"] = detail.city
            model["country"] = detail.country_name
            model["timezone"] = detail.timezone
            model["latitude"] = detail.latitude
            model["longitude"] = detail.longitude
            model["region"] = detail.region_name
            model["lang"] = detail.lang if detail.lang else "en"

        # Fetch WebGL data if necessary
        if (
            model["webgl_renderer"] is None
            or model["webgl_vendor"] is None
            or model["browser_vendor"] is None
        ):
            webgl_data = random_webgl_data()
            if webgl_data:
                if model["webgl_renderer"] is None and webgl_data.webgl_renderer:
                    model["webgl_renderer"] = webgl_data.webgl_renderer

                if model["webgl_vendor"] is None and webgl_data.webgl_vendor:
                    model["webgl_vendor"] = webgl_data.webgl_vendor

                if model["browser_vendor"] is None and webgl_data.browser_vendor:
                    model["browser_vendor"] = webgl_data.browser_vendor

        # Fetch user agent if necessary
        if model["useragent"] is None:
            useragent = random_windows_ua()
            if useragent:
                model["useragent"] = useragent

        try:
            # Remove 'id' from the dictionary if it exists
            model.pop("id", None)
            # Extract column names and values
            columns = ", ".join(model.keys())
            placeholders = ", ".join(["?"] * len(model))
            values = tuple(model.values())
            # Construct the query
            query = (
                f"INSERT OR REPLACE INTO proxies ({columns}) VALUES ({placeholders})"
            )
            execute_sql_query(query, values)
        except Exception as e:
            result["error"] = f"fetch_geo_ip fail update proxy {model['proxy']}. {e}"
            log_file(result_log_file, result["error"])
        result["data"] = model

    return result


def fetch_geo_ip_list(proxies: List[Proxy]):
    global global_tasks

    try:
        with ThreadPoolExecutor(max_workers=settings.WORKER_THREADS) as executor:
            futures = []
            for item in proxies:
                # print(f"geolocation {item.proxy}")
                futures.append(executor.submit(fetch_geo_ip, item.proxy))
            # register to global tasks
            global_tasks.update(futures)
            # Ensure all threads complete before returning
            for future in as_completed(futures):
                try:
                    future.result()
                except Exception as e:
                    print(f"Exception in future: {e}")
    except RuntimeError as e:
        print(f"RuntimeError during fetch_details_list execution: {e}")


def fetch_geo_ip_in_thread(proxies: List[Proxy]):
    thread = threading.Thread(target=fetch_geo_ip_list, args=(proxies,))
    thread.daemon = True  # Allow thread to be killed when main program exits
    thread.start()
    global_tasks.add(thread)
    return thread
