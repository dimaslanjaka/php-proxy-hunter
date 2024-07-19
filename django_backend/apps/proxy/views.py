import os
import sys
from threading import Thread, active_count
from urllib.parse import unquote
from typing import Set
from django.views.decorators.cache import cache_page

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))
from django.db.models import Q
from django.http import HttpRequest, JsonResponse
from datetime import timedelta, datetime
from .models import Proxy
from .serializers import ProxySerializer
from .tasks import *
from src.func_platform import is_django_environment
from django.shortcuts import render
from django.core.paginator import Paginator


@cache_page(60 * 15)  # Cache for 15 minutes
def index(request: HttpRequest):
    country = request.GET.get("country")
    city = request.GET.get("city")
    status = request.GET.get("status")

    # Start with the base queryset
    proxy_list = Proxy.objects.all()

    # Apply filters based on provided parameters
    if status:
        if country:
            proxy_list = proxy_list.filter(status=status, country=country)
        elif city:
            proxy_list = proxy_list.filter(status=status, city=city)
        else:
            proxy_list = proxy_list.filter(status=status)
    elif country:
        proxy_list = proxy_list.filter(country=country)
    elif city:
        proxy_list = proxy_list.filter(city=city)

    # Sort by last_check in descending order
    proxy_list = proxy_list.order_by("-last_check")

    # Paginate the results
    paginator = Paginator(proxy_list, 30)  # Show 30 proxies per page
    page_number = request.GET.get("page")
    proxies = paginator.get_page(page_number)

    return render(request, "index.html", {"proxies": proxies})


def proxies_list(request: HttpRequest):
    max = 10
    status = "all"
    proxies = None
    if request.method == "GET":
        max = int(request.GET.get("max", "10"))
        status = request.GET.get("status", "all")
    elif request.method == "POST":
        max = int(request.POST.get("max", "10"))
        status = request.POST.get("status", "all")
    else:
        return JsonResponse(
            {"error": True, "message": "Unsupported request method"}, status=405
        )

    if max == 0:
        max = 10
    if status == "all":
        proxies = Proxy.objects.all()[:max]
    elif status in ["active", "dead", "port-closed"]:
        proxies = Proxy.objects.filter(status=status)[:max]
    elif status == "private":
        proxies = Proxy.objects.filter(Q(status="private") | Q(status="true"))[:max]
    print(f"status={status} max={max} result={len(proxies)}")
    if not proxies:
        return JsonResponse({"error": "no data"})
    serializer = ProxySerializer(proxies, many=True)  # Serialize queryset

    # Alternatively, if not using DRF:
    # data = [{'proxy': proxy.proxy, 'latency': proxy.latency, ...} for proxy in proxies]

    return JsonResponse(serializer.data, safe=False)


# Set to track active proxy check threads
active_proxy_check_threads: Set[Thread] = set()


def cleanup_finished_threads():
    # Clean up finished threads from the active set
    global active_proxy_check_threads
    active_proxy_check_threads = {
        thread for thread in active_proxy_check_threads if thread.is_alive()
    }


def trigger_check_proxy(request: HttpRequest):
    global active_proxy_check_threads

    if request.method == "GET":
        # Get the proxy parameter from the query string
        proxy = request.GET.get("proxy", None)
    elif request.method == "POST":
        # Get the proxy parameter from the request body
        proxy = request.POST.get("proxy", None)
    else:
        return JsonResponse(
            {"error": True, "message": "Unsupported request method"}, status=405
        )

    if not proxy:
        count_proxies_to_check = 10
        proxies = Proxy.objects.filter(status="untested")[:count_proxies_to_check]
        if len(proxies) == 0:
            counter = 0
            now = datetime.now()
            # loop when proxies length 0
            while len(proxies) == 0 and counter < 20:
                # increase counter
                counter += 1

                # Generate a random timedelta
                random_days = random.randint(1, 30)  # Random days between 1 and 30
                random_hours = random.randint(1, 24)  # Random hours between 1 and 24

                # Adjust the timedelta accordingly
                date_ago = now - timedelta(days=random_days, hours=random_hours)

                proxies = Proxy.objects.filter(last_check__lte=date_ago)[
                    :count_proxies_to_check
                ]
        proxies = [proxy.to_json() for proxy in proxies]
        decoded_proxy = "|".join(proxies)
    else:
        # Decode the URL-encoded proxy parameter
        decoded_proxy = unquote(proxy)

    # Clean up finished threads before starting a new one
    cleanup_finished_threads()

    # Check if there are already 4 threads running
    number_threads = 4
    if len(active_proxy_check_threads) >= number_threads:
        return JsonResponse(
            {
                "error": True,
                "message": f"Maximum number of proxy check threads ({number_threads}) already running",
                "running": len(active_proxy_check_threads),
            }
        )

    # Start the proxy check in a separate thread
    thread = run_check_proxy_async_in_thread(decoded_proxy)
    active_proxy_check_threads.add(thread)

    # Gather thread details
    thread_details = {
        "id": thread.ident,
        "name": thread.name,
        "daemon": thread.daemon,
        "is_alive": thread.is_alive(),
    }

    return JsonResponse(
        {
            "error": False,
            "message": "Proxy check started in thread",
            "thread": thread_details,
        }
    )


def view_status(request: HttpRequest):
    data = {
        "is_django_env": is_django_environment(),
        "total": {
            "threads": {
                "all": active_count(),
                "proxy_checker": len(active_proxy_check_threads),
            },
            "proxies": {
                "all": Proxy.objects.all().count(),
                "untested": Proxy.objects.filter(status="untested").count(),
                "dead": Proxy.objects.filter(status="dead").count(),
                "port-closed": Proxy.objects.filter(status="port-closed").count(),
                "private": Proxy.objects.filter(
                    Q(status="private") | Q(status="true")
                ).count(),
            },
        },
    }
    return JsonResponse(data)
