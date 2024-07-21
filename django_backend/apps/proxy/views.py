import os
import sys
from threading import Thread, active_count
from typing import Set
from urllib.parse import unquote

from django.conf import settings
from django.core.paginator import EmptyPage, PageNotAnInteger, Paginator
from django.db.models import Case, IntegerField, Value, When

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

from django.core.paginator import Paginator
from django.db.models import Q
from django.http import HttpRequest, HttpResponse, JsonResponse
from django.shortcuts import redirect, render

from src.func import file_append_str, truncate_file_content
from src.func_platform import is_django_environment

from .models import Proxy
from .serializers import ProxySerializer
from .tasks import fetch_geo_ip_in_thread
from .tasks import logfile as task_log_file
from .tasks import real_check_proxy_async_in_thread


def index(request: HttpRequest):
    country = request.GET.get("country")
    city = request.GET.get("city")
    status = request.GET.get("status")
    timezone = request.GET.get("timezone")
    region = request.GET.get("region")

    # Start with the base queryset
    proxy_list = Proxy.objects.all()

    # Apply filters based on provided parameters
    if status:
        if country:
            proxy_list = proxy_list.filter(status=status, country=country)
        elif city:
            proxy_list = proxy_list.filter(status=status, city=city)
        elif timezone:
            proxy_list = proxy_list.filter(status=status, timezone=timezone)
        elif region:
            proxy_list = proxy_list.filter(status=status, region=region)
        else:
            proxy_list = proxy_list.filter(status=status)
    elif country:
        proxy_list = proxy_list.filter(country=country)
    elif city:
        proxy_list = proxy_list.filter(city=city)
    elif timezone:
        proxy_list = proxy_list.filter(timezone=timezone)
    elif region:
        proxy_list = proxy_list.filter(region=region)

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


def proxy_checker_result(request: HttpRequest):
    if request.GET.get("format") == "txt":
        # Construct the full file path
        full_file_path = os.path.join(settings.BASE_DIR, task_log_file)

        # Check if the file exists
        if not os.path.exists(full_file_path):
            file_content = f"File not found {full_file_path}"
        else:
            # Read the file content
            with open(full_file_path, "r") as file:
                file_content = file.read()

        # Return the file content in the response with the correct MIME type
        return HttpResponse(file_content, content_type="text/plain")
    return render(request, "checker_result.html")


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
    render_data = {}

    if request.method == "GET":
        # Get the proxy parameter from the query string
        proxy = request.GET.get("proxy", None)
    elif request.method == "POST":
        # Get the proxy parameter from the request body
        proxy = request.POST.get("proxy", None)
    else:
        return render_data.update(
            {"error": True, "message": "Unsupported request method"}
        )

    decoded_proxy = None
    if proxy:
        # Decode the URL-encoded proxy parameter
        decoded_proxy = unquote(proxy)

    # Clean up finished threads before starting a new one
    cleanup_finished_threads()

    # Check if there are already 4 threads running
    number_threads = 4
    if len(active_proxy_check_threads) >= number_threads:
        render_data.update(
            {
                "error": True,
                "message": f"Maximum number of proxy check threads ({number_threads}) already running",
                "running": len(active_proxy_check_threads),
            }
        )

    # Start the proxy check in a separate thread
    thread = real_check_proxy_async_in_thread(decoded_proxy)
    active_proxy_check_threads.add(thread)

    # Gather thread details
    thread_details = {
        "id": thread.ident,
        "name": thread.name,
        "daemon": thread.daemon,
        "is_alive": thread.is_alive(),
    }

    render_data.update(
        {
            "error": False,
            "message": "Proxy check started in thread",
            "thread": thread_details,
            # "data": decoded_proxy,
        }
    )

    truncate_file_content(task_log_file)

    def pretty_print(val, indent=0):
        if isinstance(val, dict):
            for k, v in val.items():
                print("\t" * indent + f"{k}:")
                file_append_str(task_log_file, "\t" * indent + f"{k}:")
                pretty_print(v, indent + 1)
        elif isinstance(val, list):
            for item in val:
                print("\t" * indent + f"{item}")
                file_append_str(task_log_file, "\t" * indent + f"{item}")
                if isinstance(item, (dict, list)):
                    pretty_print(item, indent + 1)
        else:
            print("\t" * indent + str(val))
            file_append_str(task_log_file, "\t" * indent + str(val))

    for key, val in render_data.items():
        print(f"{key}:")
        file_append_str(task_log_file, f"{key}:")
        pretty_print(val, 1)

    # return render(request, "checker_result.html", {"data": render_data})
    return redirect("/proxy/result", permanent=False)


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
