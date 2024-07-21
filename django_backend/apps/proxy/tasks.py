import os
import sys
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import List


sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))
from data.webgl import random_webgl_data
from src.func_useragent import random_windows_ua

from .models import Proxy
from .utils import get_geo_ip2
from django_backend.apps.proxy.tasks_unit.real_check_proxy import *


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
