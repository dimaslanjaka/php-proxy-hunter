import json
import os
import sys
from threading import Thread
from urllib.parse import unquote

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))

from django.http import HttpRequest, JsonResponse
from django.conf import settings
from datetime import datetime, timedelta
from .models import Proxy
from .serializers import ProxySerializer
from .tasks import *


def proxies_list(request: HttpRequest):
    proxies = Proxy.objects.all()[:10]  # Fetch first 10 proxies
    serializer = ProxySerializer(proxies, many=True)  # Serialize queryset

    # Alternatively, if not using DRF:
    # data = [{'proxy': proxy.proxy, 'latency': proxy.latency, ...} for proxy in proxies]

    return JsonResponse(serializer.data, safe=False)


def trigger_check_proxy(request: HttpRequest):
    if request.method == 'GET':
        # Get the proxy parameter from the query string
        proxy = request.GET.get('proxy', None)
    elif request.method == 'POST':
        # Get the proxy parameter from the request body
        proxy = request.POST.get('proxy', None)
    else:
        return JsonResponse({'error': True, 'message': 'Unsupported request method'}, status=405)

    if not proxy:
        # return JsonResponse({'error': True, 'message': 'Proxy parameter missing'}, status=400)
        proxies = Proxy.objects.filter(status='untested')[:100]
        if len(proxies) == 0:
            now = datetime.now(tz=settings.TIME_ZONE)
            one_week_ago = now - timedelta(weeks=1)
            # Query to filter proxies where last_check is more than 1 week ago
            proxies = Proxy.objects.filter(last_check__lte=one_week_ago)[:100]
        proxies = [proxy.to_json() for proxy in proxies]
        decoded_proxy = '|'.join(proxies)
    else:
        # Decode the URL-encoded proxy parameter
        decoded_proxy = unquote(proxy)

    # Start the proxy check in a separate thread
    thread = Thread(target=run_check_proxy_async_in_thread, args=(decoded_proxy,))
    thread.start()

    # Gather thread details
    thread_details = {
        'id': thread.ident,
        'name': thread.name,
        'native_id': thread.native_id,
        'daemon': thread.daemon,
        'is_alive': thread.is_alive(),
        'is_alive': thread.is_alive(),
        'is_alive': thread.is_alive(),
        'name': thread.name,
    }

    return JsonResponse({
        'error': False,
        'message': 'Proxy check started in thread',
        'thread': thread_details
    })
