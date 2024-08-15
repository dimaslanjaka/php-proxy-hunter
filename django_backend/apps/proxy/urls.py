import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from django.urls import path

from django_backend.apps.proxy.views import (
    index,
    proxies_list,
    proxy_checker_result,
    trigger_check_proxy,
    trigger_filter_ports_proxy,
    view_status,
)
from django_backend.apps.proxy.views_unit.geolocation import geolocation_view

app_name = "proxy"
urlpatterns = [
    path("", index, name="index"),
    path("list", proxies_list, name="proxy_list"),
    path("check", trigger_check_proxy, name="check_proxy"),
    path("status", view_status, name="checker_status"),
    path(
        "result",
        proxy_checker_result,
        name="proxy_checker_result",
    ),
    path("filter", trigger_filter_ports_proxy, name="filter_duplicate_ports"),
    path("geolocation/", geolocation_view, name="visitor_ip_geolocation"),
    path("geolocation/<data_str>", geolocation_view, name="custom_ip_geolocation"),
]
