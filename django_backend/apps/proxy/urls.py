from django.urls import path
import os, sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from . import views

app_name = "proxy"
urlpatterns = [
    path("", views.index, name="index"),
    path("list", views.proxies_list, name="proxy_list"),
    path("check", views.trigger_check_proxy, name="check_proxy"),
    path("status", views.view_status, name="checker_status"),
    path(
        "result",
        views.proxy_checker_result,
        name="proxy_checker_result",
    ),
    path("filter", views.trigger_filter_ports_proxy, name="filter_duplicate_ports"),
    path("geolocation/<proxy>", views.geolocation_view, name="geolocation"),
]
