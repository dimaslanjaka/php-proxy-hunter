import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

import threading
from concurrent.futures import Future, ThreadPoolExecutor, as_completed
from typing import List, Optional, Set, Union

from django.conf import settings
from proxy_hunter import is_valid_proxy

from data.webgl import random_webgl_data
from django_backend.apps.proxy.models import Proxy
from django_backend.apps.proxy.utils import execute_select_query, execute_sql_query
from src.func_useragent import random_windows_ua
from src.geoPlugin import get_geo_ip2

global_tasks: Set[Union[threading.Thread, Future]] = set()


def cleanup_threads():
    global global_tasks
    global_tasks = {
        task
        for task in global_tasks
        if (isinstance(task, threading.Thread) and task.is_alive())
        or (isinstance(task, Future) and not task.done())
    }


def fetch_geo_ip(proxy: Optional[str] = None):
    if not proxy:
        return
    # validate proxy
    valid = is_valid_proxy(proxy)
    if not valid:
        execute_sql_query("DELETE FROM proxies WHERE proxy = ?", (proxy,))
        print(f"{proxy} invalid - removed")
        return

    save = False
    select = execute_select_query("SELECT * FROM proxies WHERE proxy = ?", (proxy,))
    model: Optional[dict] = None
    if select:
        model = select[0]

    # Fetch geo IP details if necessary
    if model["timezone"] is None or model["country"] is None or model["lang"] is None:
        detail = get_geo_ip2(model["proxy"], model["username"], model["password"])
        if detail:
            if model["city"] is None and detail.city:
                model["city"] = detail.city
                save = True
            if model["country"] is None and detail.country_name:
                model["country"] = detail.country_name
                save = True
            if model["timezone"] is None and detail.timezone:
                model["timezone"] = detail.timezone
                save = True
            if model["latitude"] is None and detail.latitude:
                model["latitude"] = detail.latitude
                save = True
            if model["longitude"] is None and detail.longitude:
                model["longitude"] = detail.longitude
                save = True
            if model["region"] is None and detail.region_name:
                model["region"] = detail.region_name
                save = True
            if model["lang"] is None:
                model["lang"] = detail.lang if detail.lang else "en"
                save = True
        else:
            print(f"Failed to get geo IP for {model['proxy']}")

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
                save = True
            if model["webgl_vendor"] is None and webgl_data.webgl_vendor:
                model["webgl_vendor"] = webgl_data.webgl_vendor
                save = True
            if model["browser_vendor"] is None and webgl_data.browser_vendor:
                model["browser_vendor"] = webgl_data.browser_vendor
                save = True

    # Fetch user agent if necessary
    if model["useragent"] is None:
        useragent = random_windows_ua()
        if useragent:
            model["useragent"] = useragent
            save = True

    if save and model:
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
            print(f"fetch_geo_ip success {model}")
        except Exception as e:
            print(f"fetch_geo_ip fail update proxy {model['proxy']}. {e}")


def fetch_geo_ip_list(proxies: List[Proxy]):
    global global_tasks

    try:
        with ThreadPoolExecutor(max_workers=settings.WORKER_THREADS) as executor:
            futures = []
            for item in proxies:
                print(f"geolocation {item.proxy}")
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
