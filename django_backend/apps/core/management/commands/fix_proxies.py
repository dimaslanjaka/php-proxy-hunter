import os
import sys

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

from django.core.management.base import BaseCommand
from proxy_hunter import is_valid_proxy

from django_backend.apps.proxy.models import Proxy
from django_backend.apps.proxy.utils import execute_select_query, execute_sql_query


class Command(BaseCommand):
    help = "Iterate over all items in the Proxy model and validate each proxy"

    def handle(self, *args, **kwargs):
        items = execute_select_query("SELECT * FROM proxies LIMIT 1")
        for item in items:
            proxy = item["proxy"]
            if not is_valid_proxy(proxy):
                self.stdout.write(f"Invalid Proxy: {item.proxy}")
                execute_sql_query("DELETE FROM proxies WHERE proxy = ?", (proxy,))
        # Iterate over all Proxy items
        for item in Proxy.objects.all():
            # Validate each proxy
            if not is_valid_proxy(item.proxy):
                self.stdout.write(f"Invalid Proxy: {item.proxy}")
                item.delete()
                execute_sql_query(item.to_delete_sql())
