import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

from typing import Optional

from django.conf import settings
from django.core.cache import cache as django_cache
from django.http import HttpRequest, JsonResponse
from proxy_hunter.extract_proxies import *

from django_backend.apps.proxy.tasks_unit.geolocation import fetch_geo_ip
from src.func import is_debug
from src.func_proxy import build_request


def geolocation_view(request: HttpRequest, data_str: Optional[str] = None):
    result = {"error": True}
    if not data_str:
        data_str = get_client_ip(request)
    ips = extract_ips(data_str)
    ip = ips[0] if ips else None
    blacklist = settings.ALLOWED_HOSTS + ["127.0.0.1", "::1"]
    if ip in blacklist:
        print(f"{ip} is localhost")
        url = "https://cloudflare.com/cdn-cgi/trace"
        response = build_request(endpoint=url)
        text = decompress_requests_response(response)
        ips = extract_ips(text)
        ip = ips[0] if ips else None
    result.update({"ip": ip})
    if ip and ip not in blacklist:
        if not is_debug():
            cache_key = f"geolocation_{ip}"
            value: Optional[dict] = django_cache.get(cache_key)
            if value is None:
                result = fetch_geo_ip(ip)
                if result:
                    django_cache.set(cache_key, result, timeout=604800)
                else:
                    result.update(
                        {
                            "error": True,
                            "messages": f"Fail get geolocation of {ip}",
                            "data": result,
                        }
                    )
            else:
                result.update(value)
        else:
            result.update(fetch_geo_ip(ip))
        result.update({"error": False})
    return JsonResponse(result)


def get_client_ip(request: HttpRequest):
    x_forwarded_for = request.META.get("HTTP_X_FORWARDED_FOR")
    if x_forwarded_for:
        ip = x_forwarded_for.split(",")[0]
    else:
        ip = request.META.get("REMOTE_ADDR")
    return ip
