import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

from django.test import TestCase
from django_backend.apps.proxy.tasks_unit.geolocation import fetch_geo_ip
from django_backend.apps.proxy.models import Proxy as ProxyModel
from django_backend.apps.proxy.utils import execute_select_query


class FetchGeolocationTests(TestCase):
    def test_geolocation(self):
        model = execute_select_query("SELECT * FROM proxies WHERE timezone IS NULL")
        if model:
            fetch_geo_ip(model[0]["proxy"])
        else:
            print("not found data")
            print(model)
