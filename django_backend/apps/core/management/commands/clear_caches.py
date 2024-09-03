import os
import sys

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)
from django.core.cache import cache
from django.core.management.base import BaseCommand
from src.func import get_relative_path
from proxy_hunter.utils.file import delete_path
import requests_cache

# python manage.py clear_caches


class Command(BaseCommand):
    help = "Clears the cache"

    def handle(self, *args, **kwargs):
        cache.clear()
        delete_path(get_relative_path("tmp/requests_cache"))
        requests_cache.clear()
        self.stdout.write(self.style.SUCCESS("Cache cleared!"))
