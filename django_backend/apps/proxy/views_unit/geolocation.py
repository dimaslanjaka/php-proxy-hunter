import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

import io
from typing import Any, Dict, Optional

from django.conf import settings
from django.core.cache import cache as django_cache
from django.http import HttpRequest, HttpResponse, JsonResponse
from django.shortcuts import render
from django.views.decorators.cache import never_cache
from PIL import Image, ImageDraw, ImageFont
from proxy_hunter.extractor import *

from django_backend.apps.core.utils import get_query_or_post_body
from django_backend.apps.proxy.tasks_unit.geolocation import fetch_geo_ip
from src.func import get_relative_path
from proxy_hunter import md5
from src.func_platform import is_debug
from src.func_proxy import build_request


@never_cache
def geolocation_view(request: HttpRequest, data_str: Optional[str] = None):
    result = {"error": True}
    if not data_str:
        data_str = get_client_ip(request)

    ips = extract_ips(data_str)
    ip = ips[0] if ips else None
    localhosts = settings.ALLOWED_HOSTS + ["127.0.0.1", "::1"]

    if ip and ip in localhosts:
        # Check if the IP is still localhost after fetching the trace
        url = "https://cloudflare.com/cdn-cgi/trace"
        try:
            response = build_request(endpoint=url)
            text = decompress_requests_response(response)
            traced_ips = extract_ips(text)
            ip = traced_ips[0] if traced_ips else None
        except Exception:
            pass

    if not ip:
        result.update({"message": "IP or PROXY invalid"})
        return JsonResponse(result)
    # revalidate localhost
    elif ip in localhosts:
        result.update({"message": f"{ip} is localhost"})
        return JsonResponse(result)

    result.update({"ip": ip})
    if ip not in localhosts:
        if not is_debug():
            cache_key = f"geolocation_json_{ip}"
            cached_value = django_cache.get(cache_key)
            if cached_value is None:
                fetched_data = fetch_geo_ip(ip)
                if fetched_data:
                    result.update(fetched_data)
                    django_cache.set(cache_key, result, timeout=604800)
                else:
                    result.update(
                        {
                            "error": True,
                            "message": f"Failed to get geolocation for {ip}",
                        }
                    )
            else:
                result.update(
                    {
                        "data": cached_value,
                        "error": False,
                        "message": f"Cached data for {ip}",
                    }
                )
        else:
            result.update(fetch_geo_ip(ip))
            result.update({"error": False})

    if result.get("data"):
        # update data key
        data: dict = result.get("data", {})
        data.update({"ip": ip})
        lat = data.get("latitude")
        long = data.get("longitude")
        if lat and long:
            data["map"] = (
                f"https://www.google.com/maps/search/?api=1&query={lat},{long}"
            )
        result.update({"data": data})

    is_img = get_query_or_post_body(request, "img")
    is_preview_img = get_query_or_post_body(request, "preview")
    if is_preview_img is not None:
        return preview_image(request)
    if is_img is not None:
        return json_to_image_view(request, result)

    return JsonResponse(result)


def get_client_ip(request: HttpRequest):
    x_forwarded_for = request.META.get("HTTP_X_FORWARDED_FOR")
    if x_forwarded_for:
        ip = x_forwarded_for.split(",")[0]
    else:
        ip = request.META.get("REMOTE_ADDR")
    return ip


def json_to_image_view(request: HttpRequest, geo_data: Dict[str, Any]):
    # print(geo_data)
    data: dict = geo_data["data"]

    if data.get("ip"):
        cache_key = md5(f"geolocation_image_{data['ip']}")
        cache_val = django_cache.get(cache_key, None)
        if cache_val is not None and not is_debug():
            # load cached
            return HttpResponse(cache_val, content_type="image/png")

    # height = int(get_query_or_post_body(request, "h", "60") or "60")
    # width = int(get_query_or_post_body(request, "w", "480") or "480")
    height = 250
    width = 400
    image = Image.new("RGB", (width, height), color=(255, 255, 255))
    draw = ImageDraw.Draw(image)

    # Use a default font (you can specify a path to a font file if needed)
    try:
        font = ImageFont.truetype(
            get_relative_path("assets/fonts/Cera Pro Regular Italic.otf"), size=20
        )
    except Exception:
        font = ImageFont.load_default()

    def s(n: int):
        """create spaces"""
        return " " * n

    # Draw the text onto the image
    draw.text(
        (10, 10), f"IP{s(15)}{data.get('ip') or 'Unknown'}", font=font, fill=(0, 0, 0)
    )
    draw.text(
        (10, 30),
        f"Region{s(7)}{data.get('region') or data.get('region_name') or data.get('region_code') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 50),
        f"City{s(11)}{data.get('city') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 70),
        f"Country{s(5)}{data.get('country') or data.get('country_name') or data.get('country_code') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 90),
        f"Latitude{s(5)}{data.get('latitude') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 110),
        f"Longitude{s(3)}{data.get('longitude') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 130),
        f"Timezone{s(3)}{data.get('timezone') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 150),
        f"Locale{s(7)}{data.get('lang') or 'Unknown'}",
        font=font,
        fill=(0, 0, 0),
    )
    draw.text(
        (10, 180),
        "Copyright: L3n4r0x\ndimaslanjaka@gmail.com\nhttps://www.webmanajemen.com",
        font=font,
        fill=(0, 0, 0),
    )

    # Save the image to a BytesIO object
    img_io = io.BytesIO()
    image.save(img_io, "PNG")
    img_io.seek(0)

    # save cache
    django_cache.set(cache_key, img_io.getvalue(), timeout=604800)

    # Return the image as an HTTP response
    return HttpResponse(img_io.getvalue(), content_type="image/png")


def preview_image(request: HttpRequest):
    return render(request, "geolocation_image.html")
