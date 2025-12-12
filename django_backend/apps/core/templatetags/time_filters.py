from typing import Optional, Union
from datetime import datetime
from django import template
from django.utils import timezone
from dateutil.parser import parse
from django.conf import settings
import pytz

from src.utils.date.timeAgo import time_ago as time_ago_util

register = template.Library()


@register.filter
def time_ago(value: Optional[Union[str, datetime]] = None):
    """Template filter wrapper that parses/localizes input using Django
    timezone settings and then calls the framework-agnostic `time_ago` utility.
    """
    if not value:
        return "-"

    # Accept either an aware datetime or a parsable string
    if isinstance(value, datetime):
        dt = value
    else:
        try:
            dt = parse(str(value))
        except Exception:
            return str(value)

    # If naive, try to make it aware using Django timezone; fall back to settings
    if timezone.is_naive(dt):
        try:
            tz = timezone.get_default_timezone()
            dt = timezone.make_aware(dt, tz)
        except Exception:
            tz_name = getattr(settings, "TIME_ZONE", "UTC")
            dt = pytz.timezone(tz_name).localize(dt)

    return time_ago_util(dt)
