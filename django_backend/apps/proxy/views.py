import os
import sys
from urllib.parse import unquote

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))

from django.http import HttpRequest, HttpResponse, JsonResponse

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

    if proxy is None:
        return JsonResponse({'error': 'Proxy parameter missing'}, status=400)

    thread = run_check_proxy_async_in_thread(unquote(proxy))
    return HttpResponse(f"Proxy check started in thread: {thread.name}")
