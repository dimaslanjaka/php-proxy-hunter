from django.core.management.base import BaseCommand
from django.core.cache import cache

# python manage.py clear_cache


class Command(BaseCommand):
    help = "Clears the cache"

    def handle(self, *args, **kwargs):
        cache.clear()
        self.stdout.write(self.style.SUCCESS("Cache cleared!"))
