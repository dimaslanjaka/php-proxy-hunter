from django import template
from django.utils import timezone
from dateutil.parser import parse
from django.utils.timesince import timesince

register = template.Library()


@register.filter
def time_ago(date_str):
    if not date_str:
        return "-"

    try:
        # Parse RFC3339 date string
        date = parse(date_str)
        # Get the current time
        now = timezone.now()
        # Calculate the time difference
        diff = now - date
        # Convert time difference to human-readable format
        return timesince(date, now).replace("days", "d").replace("day", "d") + " ago"
    except (ValueError, TypeError):
        return "-"
