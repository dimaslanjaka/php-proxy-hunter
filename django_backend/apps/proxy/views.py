import os
import sys

from django.http import JsonResponse
from django.shortcuts import render

from .tasks import debug_task

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))
from django.http import JsonResponse

from . import models
from . import serializers as cserializer
from .tasks import doCrawl


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
