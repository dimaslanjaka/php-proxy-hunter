import os
import sys

# Assuming this script is located in django_backend/apps/core/management/commands/check_proxy_async.py
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../../../'))
SRC_DIR = os.path.join(BASE_DIR, 'src')
sys.path.append(SRC_DIR)

from django.core.management.base import BaseCommand

from src.func_proxy import \
    check_proxy_new  # Adjust import path as per your actual function location


class Command(BaseCommand):
    """
    python manage.py check_proxy_async 72.10.160.91:28577
    """
    help = 'Checks the status of a proxy asynchronously'

    def add_arguments(self, parser):
        parser.add_argument('proxy', type=str, help='Proxy address in IP:PORT format')

    def handle(self, *args, **kwargs):
        proxy = kwargs['proxy']
        check_proxy_new(proxy)
        self.stdout.write(self.style.SUCCESS(f"Proxy check task completed successfully for {proxy}"))
