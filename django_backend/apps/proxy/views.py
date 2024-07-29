import json
import os
import sys
from threading import Thread, active_count
from typing import Any, Dict, Set
from urllib.parse import unquote
from django.views.decorators.cache import never_cache
from django.conf import settings
from django.core.paginator import EmptyPage, PageNotAnInteger, Paginator
from django.db.models import Case, IntegerField, Value, When

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

from django.core.paginator import Paginator
from django.db.models import Q
from django.http import HttpRequest, HttpResponse, JsonResponse
from django.shortcuts import render

from src.func import file_append_str, get_relative_path, truncate_file_content
from src.func_platform import is_django_environment
from src.func_console import log_file

from .models import Proxy
from .serializers import ProxySerializer
from django_backend.apps.proxy.tasks_unit.geolocation import (
    global_tasks as tasks_geolocation,
    fetch_geo_ip_in_thread,
    cleanup_threads as tasks_geolocation_cleanup,
)
from django_backend.apps.proxy.tasks_unit.filter_ports_proxy import (
    start_filter_duplicates_ips,
    start_check_open_ports,
    global_tasks as filter_ports_threads,
)
from django_backend.apps.proxy.tasks_unit.real_check_proxy import (
    real_check_proxy_async_in_thread,
    result_log_file as proxy_checker_task_log_file,
    global_tasks as proxy_checker_threads,
    cleanup_threads as tasks_checker_cleanup,
)


def index(request: HttpRequest):
    # Get query parameters
    filters = {
        "country": request.GET.get("country"),
        "city": request.GET.get("city"),
        "status": request.GET.get("status"),
        "timezone": request.GET.get("timezone"),
        "region": request.GET.get("region"),
        "type": request.GET.get("type"),
        "https": request.GET.get("https"),
    }
    search_query = request.GET.get("search")
    page_title = "Free Premium Proxy Lists"

    # Build the query
    query = Q()
    if filters["status"]:
        query &= Q(status=filters["status"])
    if filters["country"]:
        page_title = f"{filters['country']} Proxy List"
        query &= Q(country=filters["country"])
    if filters["city"]:
        page_title = f"{filters['city']} Proxy List"
        query &= Q(city=filters["city"])
    if filters["timezone"]:
        page_title = f"Proxy List Timezone {filters['timezone']}"
        query &= Q(timezone=filters["timezone"])
    if filters["region"]:
        page_title = f"{filters['region']} Proxy List"
        query &= Q(region=filters["region"])
    if filters["type"]:
        page_title = f"{filters['type'].upper()} Proxy List"
        query &= Q(type__icontains=filters["type"])
    if filters["https"]:
        page_title = "HTTPS/SSL Proxy List"
        query &= Q(https__icontains="true")
    if search_query:
        query &= Q(
            Q(proxy__icontains=search_query)
            | Q(region__icontains=search_query)
            | Q(city__icontains=search_query)
            | Q(country__icontains=search_query)
            | Q(timezone__icontains=search_query)
        )

    # Apply filters and annotations
    proxy_list = (
        Proxy.objects.filter(query)
        .annotate(
            is_active=Case(
                When(status="active", then=Value(1)),
                default=Value(0),
                output_field=IntegerField(),
            )
        )
        .order_by("-is_active", "-last_check")
    )

    # Paginate the results
    paginator = Paginator(proxy_list, 30)
    page_number = request.GET.get("page")

    try:
        if page_number:
            page_title += f" Page {page_number}"
        proxies = paginator.page(page_number)
    except PageNotAnInteger:
        proxies = paginator.page(1)
    except EmptyPage:
        proxies = paginator.page(paginator.num_pages)

    # Fetch missing details in a background thread
    fetch_geo_ip_in_thread(proxies.object_list)

    return render(
        request,
        "proxy_list_index.html",
        {"request": request, "proxies": proxies, "page_title": page_title},
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

    # Clean up finished threads
    cleanup_threads()

    # Check if there are already [n] threads running
    # skip limitation for admin
    is_admin = bool(
        request.user.is_authenticated
        and request.user.is_staff
        and settings.UNLIMITED_FOR_ADMIN
    )
    # Debug logs
    render_data.update(
        {
            "user": {
                "authenticated": request.user.is_authenticated,
                "staff": request.user.is_staff,
                "unlimited": settings.UNLIMITED_FOR_ADMIN,
            }
        }
    )
    if len(proxy_checker_threads) >= settings.LIMIT_THREADS and not is_admin:
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

    return JsonResponse(render_data)


def trigger_filter_ports_proxy(request: HttpRequest):
    global filter_ports_threads
    truncate_file_content(proxy_checker_task_log_file)

    # clean up threads
    cleanup_threads()
    render_data = {"running": len(filter_ports_threads)}
    # limit threads to filter duplicate ports
    # skip limitation for admin
    is_admin = bool(
        request.user.is_authenticated
        and request.user.is_staff
        and settings.UNLIMITED_FOR_ADMIN
    )
    # Debug logs
    render_data.update(
        {
            "user": {
                "authenticated": request.user.is_authenticated,
                "staff": request.user.is_staff,
                "unlimited": settings.UNLIMITED_FOR_ADMIN,
            }
        }
    )
    if len(filter_ports_threads) > settings.LIMIT_THREADS and not is_admin:
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
    return JsonResponse(render_data)


def cleanup_threads():
    global proxy_checker_threads, filter_ports_threads
    tasks_checker_cleanup()
    tasks_geolocation_cleanup()
    proxy_checker_threads = {
        thread for thread in proxy_checker_threads if thread.is_alive()
    }
    filter_ports_threads = {
        thread for thread in filter_ports_threads if thread.is_alive()
    }


@never_cache
def view_status(request: HttpRequest):
    global proxy_checker_threads, filter_ports_threads
    cleanup_threads()
    data = {
        "is_django_env": is_django_environment(),
        "SID": request.session.session_key,
        "total": {
            "threads": {
                "all": active_count(),
                "proxy_checker": len(proxy_checker_threads),
                "filter_duplicates": len(filter_ports_threads),
                "geolocation": len(tasks_geolocation),
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
    response = JsonResponse(data)
    response["Cache-Control"] = "no-cache, no-store, must-revalidate"  # HTTP 1.1.
    response["Pragma"] = "no-cache"  # HTTP 1.0.
    response["Expires"] = "0"  # Proxies.
    return response
