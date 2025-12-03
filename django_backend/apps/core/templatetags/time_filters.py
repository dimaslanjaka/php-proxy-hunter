from typing import Optional
from django import template
from django.utils import timezone
from dateutil.parser import parse
from django.utils.timesince import timesince
from datetime import datetime
from django.conf import settings
import pytz

register = template.Library()


# @register.filter
# def time_ago(date_str):
#     if not date_str:
#         return "-"
#     try:
#         # Parse RFC3339 date string
#         date = parse(date_str)
#         # Get the current time
#         now = timezone.now()
#         # Calculate the time difference
#         diff = now - date
#         # Convert time difference to human-readable format
#         return timesince(date, now).replace("days", "d").replace("day", "d") + " ago"
#     except (ValueError, TypeError):
#         return "-"


from src.utils.date.timeAgo import time_ago as time_ago_util


@register.filter
def time_ago(date_str: Optional[str] = None):
    """Template filter wrapper that reuses the core `time_ago` utility.

    Keeps backward compatibility for template usage.
    """
    return time_ago_util(date_str)
