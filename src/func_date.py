from datetime import datetime, timezone
from typing import Optional
from dateutil import parser
from tzlocal import get_localzone
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


def is_current_time_more_than_rfc3339(given_datetime_str: str) -> Optional[bool]:
    """
    Checks if the current date and time are more than the given date and time in RFC 3339 format.

    :param given_datetime_str: str, date and time in RFC 3339 format
    :return: bool, True if current date and time are more than the given date and time, otherwise False
    """
    try:
        # Parse the given date and time
        given_datetime = parser.isoparse(given_datetime_str)

        # Get the current date and time with the same timezone as the given date and time
        current_datetime = datetime.now(given_datetime.tzinfo)

        # Compare the current date and time with the given date and time
        return current_datetime > given_datetime
    except Exception as e:
        print(f"Error parsing date: {e}")
        return None


def get_current_rfc3339_time(use_utc=False):
    """
    Returns the current date and time formatted according to RFC 3339.

    Args:
    use_utc (bool): If True, the time will be in UTC. If False, the local timezone will be used.

    Returns:
    str: Current date and time in RFC 3339 format.
    """
    if use_utc:
        now = datetime.now(timezone.utc)
        rfc3339_timestamp = now.strftime("%Y-%m-%dT%H:%M:%SZ")
    else:
        now = datetime.now(get_localzone())
        rfc3339_timestamp = now.strftime("%Y-%m-%dT%H:%M:%S%z")

    return rfc3339_timestamp
