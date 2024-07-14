import os
import sys
from threading import Thread, active_count
from urllib.parse import unquote
from typing import Set
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))
from django.db.models import Q
from django.http import HttpRequest, JsonResponse
from datetime import timedelta, timezone, datetime
from .models import Proxy
from .serializers import ProxySerializer
from .tasks import *
from src.func_platform import is_django_environment


def proxies_list(request: HttpRequest):
    proxies = Proxy.objects.all()[:10]  # Fetch first 10 proxies
    serializer = ProxySerializer(proxies, many=True)  # Serialize queryset

    # Alternatively, if not using DRF:
    # data = [{'proxy': proxy.proxy, 'latency': proxy.latency, ...} for proxy in proxies]

    return JsonResponse(serializer.data, safe=False)


# Set to track active proxy check threads
active_proxy_check_threads: Set[Thread] = set()


def cleanup_finished_threads():
    # Clean up finished threads from the active set
    global active_proxy_check_threads
    active_proxy_check_threads = {thread for thread in active_proxy_check_threads if thread.is_alive()}


def trigger_check_proxy(request: HttpRequest):
    global active_proxy_check_threads

    if request.method == 'GET':
        # Get the proxy parameter from the query string
        proxy = request.GET.get('proxy', None)
    elif request.method == 'POST':
        # Get the proxy parameter from the request body
        proxy = request.POST.get('proxy', None)
    else:
        return JsonResponse({'error': True, 'message': 'Unsupported request method'}, status=405)

    if not proxy:
        count_proxies_to_check = 10
        proxies = Proxy.objects.filter(status='untested')[:count_proxies_to_check]
        if len(proxies) == 0:
            now = datetime.now()
            # loop when proxies length 0
            while len(proxies) == 0:
                # Generate a random timedelta
                random_days = random.randint(1, 30)  # Random days between 1 and 30
                random_hours = random.randint(1, 24)  # Random hours between 1 and 24

                # Adjust the timedelta accordingly
                date_ago = now - timedelta(days=random_days, hours=random_hours)

                proxies = Proxy.objects.filter(last_check__lte=date_ago)[:count_proxies_to_check]
        proxies = [proxy.to_json() for proxy in proxies]
        decoded_proxy = '|'.join(proxies)
    else:
        # Decode the URL-encoded proxy parameter
        decoded_proxy = unquote(proxy)

    # Clean up finished threads before starting a new one
    cleanup_finished_threads()

    # Check if there are already 4 threads running
    number_threads = 4
    if len(active_proxy_check_threads) >= number_threads:
        return JsonResponse({
            'error': True,
            'message': f'Maximum number of proxy check threads ({number_threads}) already running',
            'running': len(active_proxy_check_threads)
        })

    # Start the proxy check in a separate thread
    thread = run_check_proxy_async_in_thread(decoded_proxy)
    active_proxy_check_threads.add(thread)

    # Gather thread details
    thread_details = {
        'id': thread.ident,
        'name': thread.name,
        'daemon': thread.daemon,
        'is_alive': thread.is_alive(),
    }

    return JsonResponse({
        'error': False,
        'message': 'Proxy check started in thread',
        'thread': thread_details
    })


def view_status(request: HttpRequest):
    data = {
        'is_django_env': is_django_environment(),
        "total": {
            'threads': {
                'all': active_count(),
                'proxy_checker': len(active_proxy_check_threads)
            },
            'proxies': {
                'all': Proxy.objects.all().count(),
                'untested': Proxy.objects.filter(status='untested').count(),
                'dead': Proxy.objects.filter(status='dead').count(),
                'port-closed': Proxy.objects.filter(status='port-closed').count(),
                'private': Proxy.objects.filter(Q(status='private') | Q(status='true')).count()
            }
        }
    }
    return JsonResponse(data)
