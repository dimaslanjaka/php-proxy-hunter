from django.core.management.base import BaseCommand
from django.utils import timezone


class Command(BaseCommand):
    """
    python manage.py what_time_is_it
    """
    help = 'Displays current time'

    def handle(self, *args, **kwargs):
        time = timezone.now().strftime('%X')
        self.stdout.write("It's now %s" % time)
