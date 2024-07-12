from django.http import JsonResponse
import sys, os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))
from . import models
from . import serializers as cserializer


def proxies_list(request):
    proxies = models.Proxy.objects.all()[:10]  # Fetch first 10 proxies
    serializer = cserializer.ProxySerializer(proxies, many=True)  # Serialize queryset

    # Alternatively, if not using DRF:
    # data = [{'proxy': proxy.proxy, 'latency': proxy.latency, ...} for proxy in proxies]

    return JsonResponse(serializer.data, safe=False)
