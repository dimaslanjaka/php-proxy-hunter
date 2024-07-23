import json
import os
import sys
from threading import Thread, active_count
from typing import Any, Dict, Set
from urllib.parse import unquote

from django.conf import settings
from django.core.paginator import EmptyPage, PageNotAnInteger, Paginator
from django.db.models import Case, IntegerField, Value, When

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

from django.core.paginator import Paginator
from django.db.models import Q
from django.http import HttpRequest, HttpResponse, JsonResponse
from django.shortcuts import redirect, render

from src.func import file_append_str, get_relative_path, truncate_file_content
from src.func_platform import is_django_environment
from src.func_console import log_file

from .models import Proxy
from .serializers import ProxySerializer
from .tasks import fetch_geo_ip_in_thread
from .tasks import result_log_file as proxy_checker_task_log_file
from .tasks import real_check_proxy_async_in_thread
from .tasks import start_filter_duplicates_ips, start_check_open_ports


def index(request: HttpRequest):
    country = request.GET.get("country")
    city = request.GET.get("city")
    status = request.GET.get("status")
    timezone = request.GET.get("timezone")
    region = request.GET.get("region")
    search_query = request.GET.get("search")

    # Apply filters based on provided parameters
    if status:
        if country:
            proxy_list = Proxy.objects.filter(status=status, country=country)
        elif city:
            proxy_list = Proxy.objects.filter(status=status, city=city)
        elif timezone:
            proxy_list = Proxy.objects.filter(status=status, timezone=timezone)
        elif region:
            proxy_list = Proxy.objects.filter(status=status, region=region)
        else:
            proxy_list = Proxy.objects.filter(status=status)
    elif country:
        proxy_list = Proxy.objects.filter(country=country)
    elif city:
        proxy_list = Proxy.objects.filter(city=city)
    elif timezone:
        proxy_list = Proxy.objects.filter(timezone=timezone)
    elif region:
        proxy_list = Proxy.objects.filter(region=region)
    elif search_query:
        proxy_list = Proxy.objects.filter(
            Q(proxy__icontains=search_query)
            | Q(region__icontains=search_query)
            | Q(city__icontains=search_query)
            | Q(country__icontains=search_query)
            | Q(timezone__icontains=search_query)
        )
    else:
        proxy_list = Proxy.objects.all()

    # Annotate the queryset with a custom field for sorting
    proxy_list = proxy_list.annotate(
        is_active=Case(
            When(status="active", then=Value(1)),
            default=Value(0),
            output_field=IntegerField(),
        )
    ).order_by(
        "-is_active", "-last_check"
    )  # First by is_active, then by last_check

    # Paginate the results
    paginator = Paginator(proxy_list, 30)  # Show 30 proxies per page
    page_number = request.GET.get("page")

    try:
        proxies = paginator.page(page_number)
    except PageNotAnInteger:
        proxies = paginator.page(1)
    except EmptyPage:
        proxies = paginator.page(paginator.num_pages)

    # Fetch missing details in a background thread
    fetch_geo_ip_in_thread(proxies.object_list)

    return render(
        request, "proxy_list_index.html", {"request": request, "proxies": proxies}
    )


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


def proxy_checker_result(request: HttpRequest):
    if request.GET.get("format") == "txt":
        # Construct the full file path
        full_file_path = os.path.join(settings.BASE_DIR, proxy_checker_task_log_file)

        # Check if the file exists
        if not os.path.exists(full_file_path):
            file_content = f"File not found {full_file_path}"
        else:
            # Read the file content
            with open(full_file_path, "r") as file:
                file_content = file.read()

        # Return the file content in the response with the correct MIME type
        return HttpResponse(file_content, content_type="text/plain")
    return render(request, "proxy_checker_result.html")


# Set to track active proxy check threads
proxy_checker_threads: Set[Thread] = set()


def print_dict(data: Dict[str, Any]):
    def pretty_print(val, indent=0):
        if isinstance(val, dict):
            for k, v in val.items():
                # Print the key and its value if not a dict or list
                if isinstance(v, (dict, list)):
                    log_file(proxy_checker_task_log_file, "\t" * indent + f"{k}:")
                    pretty_print(v, indent + 1)
                else:
                    log_file(proxy_checker_task_log_file, "\t" * indent + f"{k}: {v}")
        elif isinstance(val, list):
            for item in val:
                # Handle list items
                if isinstance(item, (dict, list)):
                    log_file(proxy_checker_task_log_file, "\t" * indent + "-")
                    pretty_print(item, indent + 1)
                else:
                    log_file(
                        proxy_checker_task_log_file, "\t" * indent + "- " + str(item)
                    )
        else:
            log_file(proxy_checker_task_log_file, "\t" * indent + str(val))

    # Start with the top-level items
    for key, val in data.items():
        if isinstance(val, (dict, list)):
            log_file(proxy_checker_task_log_file, f"{key}:")
            pretty_print(val, 1)
        else:
            log_file(proxy_checker_task_log_file, f"{key}: {val}")


def get_thread_details(thread: Thread):
    return {
        "id": thread.ident,
        "name": thread.name,
        "daemon": thread.daemon,
        "is_alive": thread.is_alive(),
    }


def trigger_check_proxy(request: HttpRequest):
    global proxy_checker_threads

    truncate_file_content(proxy_checker_task_log_file)

    render_data = {
        "running": len(proxy_checker_threads),
    }

    if request.method == "GET":
        # Get the proxy parameter from the query string
        proxy = request.GET.get("proxy", None)

    elif request.method == "POST":
        content_type = request.content_type

        if content_type == "application/json":
            try:
                # Parse JSON data from the request body
                data = json.loads(request.body)
                proxy = data.get("proxy", None)
            except json.JSONDecodeError:
                return JsonResponse({"error": "Invalid JSON"}, status=400)

        elif content_type == "application/x-www-form-urlencoded":
            # Parse form data
            proxy = request.POST.get("proxy", None)
    else:
        return render_data.update(
            {"error": True, "message": "Unsupported request method"}
        )

    decoded_proxy = None
    if proxy:
        # Decode the URL-encoded proxy parameter
        decoded_proxy = unquote(proxy)
        # save uploaded proxies
        file_append_str(get_relative_path("proxies.txt"), f"\n{decoded_proxy}\n")

    # Clean up finished threads from the active set before starting a new one
    proxy_checker_threads = {
        thread for thread in proxy_checker_threads if thread.is_alive()
    }

    # Check if there are already [n] threads running
    if len(proxy_checker_threads) >= settings.LIMIT_THREADS:
        render_data.update(
            {
                "error": True,
                "message": f"Maximum number of proxy check threads ({settings.LIMIT_THREADS}) already running",
            }
        )
    else:
        # Start the proxy check in a separate thread
        thread = real_check_proxy_async_in_thread(decoded_proxy)
        proxy_checker_threads.add(thread)

        render_data.update(
            {
                "error": False,
                "message": "Proxy check started in thread",
                "thread": get_thread_details(thread),
                # "data": decoded_proxy,
            }
        )

    print_dict(render_data)

    # return render(request, "checker_result.html", {"data": render_data})
    return redirect("/proxy/result", permanent=False)


filter_ports_threads: Set[Thread] = set()


def trigger_filter_ports_proxy(request: HttpRequest):
    global filter_ports_threads
    truncate_file_content(proxy_checker_task_log_file)

    # clean up threads
    filter_ports_threads = {
        thread for thread in filter_ports_threads if thread.is_alive()
    }
    render_data = {"running": len(filter_ports_threads)}
    # limit threads to filter duplicate ports
    if len(filter_ports_threads) > settings.LIMIT_THREADS:
        render_data.update(
            {
                "error": True,
                "messages": f"Maximum number of filter duplicated ports threads ({settings.LIMIT_THREADS}) already running",
            }
        )
    else:
        thread1 = start_filter_duplicates_ips()
        filter_ports_threads.add(thread1)
        thread2 = start_check_open_ports()
        filter_ports_threads.add(thread2)
        render_data.update(
            {
                "error": False,
                "messages": "filter duplicate ips thread started",
                "thread": {
                    "filter-open-ports": get_thread_details(thread1),
                    "check-open-ports": get_thread_details(thread2),
                },
            }
        )
    print_dict(render_data)
    return redirect("/proxy/result", permanent=False)


def view_status(request: HttpRequest):
    global proxy_checker_threads, filter_ports_threads
    proxy_checker_threads = {
        thread for thread in proxy_checker_threads if thread.is_alive()
    }
    filter_ports_threads = {
        thread for thread in filter_ports_threads if thread.is_alive()
    }
    data = {
        "is_django_env": is_django_environment(),
        "total": {
            "threads": {
                "all": active_count(),
                "proxy_checker": len(proxy_checker_threads),
                "filter_duplicates": len(filter_ports_threads),
            },
            "proxies": {
                "all": Proxy.objects.all().count(),
                "untested": Proxy.objects.filter(status="untested").count(),
                "dead": Proxy.objects.filter(status="dead").count(),
                "port-closed": Proxy.objects.filter(status="port-closed").count(),
                "private": Proxy.objects.filter(
                    Q(status="private") | Q(status="true")
                ).count(),
                "active": Proxy.objects.filter(status="active").count(),
            },
        },
    }
    return JsonResponse(data)
