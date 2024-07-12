import os
import sys

from django.http import JsonResponse

from .tasks import check_proxy_async, check_proxy_task, debug_task

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))
from django.http import JsonResponse

from . import models
from . import serializers as cserializer


def proxies_list(request):
    proxies = models.Proxy.objects.all()[:10]  # Fetch first 10 proxies
    serializer = cserializer.ProxySerializer(proxies, many=True)  # Serialize queryset

    # Alternatively, if not using DRF:
    # data = [{'proxy': proxy.proxy, 'latency': proxy.latency, ...} for proxy in proxies]

    return JsonResponse(serializer.data, safe=False)


def test_celery(request):
    result = debug_task.delay()

    # Poll the task status (example)
    if result.ready():
        response_data = {
            'task_id': result.id,
            'status': result.status,
            'result': result.get()  # Retrieve the task result
        }
    else:
        response_data = {
            'task_id': result.id,
            'status': result.status,
            'result': None
        }

    return JsonResponse(response_data)


def trigger_check_proxy(request):
    # Get the proxy parameter from the query string
    proxy = request.GET.get('proxy', None)

    if proxy is None:
        return JsonResponse({'error': 'Proxy parameter missing'}, status=400)

    # Call the Celery task asynchronously
    result = check_proxy_async.delay(proxy)

    # Return a JSON response with the task ID
    return JsonResponse({'task_id': result.id})
