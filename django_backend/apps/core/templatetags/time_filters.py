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


@register.filter
def time_ago(date_string):
    """
    Converts a given date string to a human-readable "time ago" format.

    Parameters:
    date_string (str): The date string to be converted.

    Returns:
    str: The time ago format of the provided date string.
    """
    try:
        # Convert the provided date string to a datetime object
        date = datetime.fromisoformat(date_string)

        # If date is naive (no timezone info), assume it is in the local timezone
        if date.tzinfo is None:
            local_tz = pytz.timezone(settings.TIME_ZONE)
            date = local_tz.localize(date)
    except ValueError:
        # Return invalid date to original string
        return date_string

    # Get the current time in the local timezone
    now = timezone.now()

    # Calculate the time difference
    difference = now - date

    # Convert difference to seconds, minutes, hours, and days
    seconds = difference.total_seconds()
    minutes = seconds // 60
    hours = minutes // 60
    days = hours // 24

    # Calculate remaining hours, minutes, and seconds
    remaining_hours = hours % 24
    remaining_minutes = minutes % 60
    remaining_seconds = seconds % 60

    # Construct the ago time string
    ago_time = ""
    if days > 0:
        ago_time += f"{int(days)} day{'s' if days != 1 else ''} "
        if remaining_hours > 0:
            ago_time += (
                f"{int(remaining_hours)} hour{'s' if remaining_hours != 1 else ''} "
            )
    elif remaining_hours > 0:
        ago_time += f"{int(remaining_hours)} hour{'s' if remaining_hours != 1 else ''} "
        if remaining_minutes > 0:
            ago_time += f"{int(remaining_minutes)} minute{'s' if remaining_minutes != 1 else ''} "
    elif remaining_minutes > 0:
        ago_time += (
            f"{int(remaining_minutes)} minute{'s' if remaining_minutes != 1 else ''} "
        )
        if remaining_seconds > 0:
            ago_time += f"{int(remaining_seconds)} second{'s' if remaining_seconds != 1 else ''} "
    elif remaining_seconds > 0:
        ago_time += (
            f"{int(remaining_seconds)} second{'s' if remaining_seconds != 1 else ''} "
        )

    # Append "ago" to the ago time string
    ago_time += "ago"

    return ago_time
