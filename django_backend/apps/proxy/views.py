import os
import sys
from typing import Any, Dict, List

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))
from threading import Thread, active_count
from urllib.parse import unquote

from django.conf import settings
from django.db.models import Q
from django.http import HttpRequest, HttpResponse, JsonResponse
from django.shortcuts import render
from django.views.decorators.cache import never_cache
from proxy_hunter import file_append_str, truncate_file_content

from django_backend.apps.core.models import ProcessStatus
from django_backend.apps.core.utils import get_query_or_post_body
from django_backend.apps.proxy.tasks_unit.filter_ports_proxy import (
    cleanup_threads as tasks_filter_cleanup,
)
from django_backend.apps.proxy.tasks_unit.filter_ports_proxy import (
    global_tasks as filter_ports_threads,
)
from django_backend.apps.proxy.tasks_unit.filter_ports_proxy import (
    start_check_open_ports,
    start_filter_duplicates_ips,
)
from django_backend.apps.proxy.tasks_unit.geolocation import (
    cleanup_threads as tasks_geolocation_cleanup,
)
from django_backend.apps.proxy.tasks_unit.geolocation import (
    global_tasks as tasks_geolocation,
)
from django_backend.apps.proxy.tasks_unit.real_check_proxy import (
    cleanup_threads as tasks_checker_cleanup,
)
from django_backend.apps.proxy.tasks_unit.real_check_proxy import (
    global_tasks as proxy_checker_threads,
)
from django_backend.apps.proxy.tasks_unit.real_check_proxy import (
    reak_check_proxy_huey,
    real_check_proxy_async_in_thread,
)
from django_backend.apps.proxy.tasks_unit.real_check_proxy import (
    result_log_file as proxy_checker_task_log_file,
)
from django_backend.apps.proxy.views_unit.proxy import get_page_title, get_proxy_list
from src.func import get_relative_path
from src.func_console import log_file
from src.func_date import get_current_rfc3339_time
from src.func_platform import is_django_environment

from .models import Proxy
from .serializers import ProxySerializer


def index(request: HttpRequest):
    page_title = get_page_title(request)
    proxies, pagination_title = get_proxy_list(request)
    return render(
        request,
        "proxy_list_index.html",
        {
            "request": request,
            "proxies": proxies,
            "page_title": f"{page_title} {pagination_title}".strip(),
        },
    )


def proxies_list(request: HttpRequest):
    proxies, _ = get_proxy_list(request)
    if not proxies:
        return JsonResponse({"error": "no data"})
    serializer = ProxySerializer(proxies, many=True)  # Serialize queryset

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
            with open(full_file_path, "r", encoding="utf-8") as file:
                file_content = file.read()

        # Return the file content in the response with the correct MIME type
        return HttpResponse(file_content, content_type="text/plain")
    return render(request, "proxy_checker_result.html")


def print_dict(data: Dict[str, Any]):
    def pretty_print(val, indent=0):
        if isinstance(val, Dict):
            for k, v in val.items():
                # Print the key and its value if not a dict or list
                if isinstance(v, (Dict, List)):
                    log_file(proxy_checker_task_log_file, "\t" * indent + f"{k}:")
                    pretty_print(v, indent + 1)
                else:
                    log_file(proxy_checker_task_log_file, "\t" * indent + f"{k}: {v}")
        elif isinstance(val, List):
            for item in val:
                # Handle list items
                if isinstance(item, (Dict, List)):
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
        if isinstance(val, (Dict, List)):
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
    render_data = {
        "running": len(proxy_checker_threads),
        "date": get_current_rfc3339_time(),
    }

    # ?proxy=IP:PORT to check custom proxy
    proxy = get_query_or_post_body(request, "proxy")
    # ?force=true to force run
    process_force_run = get_query_or_post_body(request, "force")
    render_data.update({"lock": "applied" if not process_force_run else "force"})
    if not proxy:
        render_data.update({"message": "Checking existing proxies"})
        # lock process
        process_status, process_created = ProcessStatus.objects.get_or_create(
            process_name="check_existing_proxies"
        )
        if process_force_run:
            process_status.is_done = True
            process_status.save()
        if not process_status.is_done and not process_created and not process_force_run:
            render_data.update(
                {"message": f"Another checker running at {process_status.timestamp}"}
            )
            return JsonResponse(render_data)
        else:
            process_status.is_done = False
            process_status.save()
    else:
        render_data.update({"message": "Checking custom proxies"})

    truncate_file_content(proxy_checker_task_log_file)

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
        # move task into huey background task when main thread has limit
        reak_check_proxy_huey(decoded_proxy)
    else:
        # Start the proxy check in a separate thread
        thread = real_check_proxy_async_in_thread(decoded_proxy)
        proxy_checker_threads.add(thread)

        render_data.update(
            {
                "error": False,
                "thread": get_thread_details(thread),
                # "data": decoded_proxy,
            }
        )

    print_dict(render_data)

    return JsonResponse(render_data)


def trigger_filter_ports_proxy(request: HttpRequest):
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
    tasks_checker_cleanup()
    tasks_geolocation_cleanup()
    tasks_filter_cleanup()
    # proxy_checker_threads = {
    #     thread for thread in proxy_checker_threads if thread.is_alive()
    # }
    # filter_ports_threads = {
    #     thread for thread in filter_ports_threads if thread.is_alive()
    # }


@never_cache
def view_status(request: HttpRequest):
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
                "port-open": Proxy.objects.filter(status="port-open").count(),
                "private": Proxy.objects.filter(
                    Q(status="private") | Q(private="true")
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
