from datetime import datetime, timedelta, timezone
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


def is_date_rfc3339_older_than(date_str: str, hours: int = 1) -> bool:
    """
    Check if the given RFC 3339 timestamp is older than a specified number of hours.

    Args:
        date_str (str): The RFC 3339 timestamp string with timezone offset.
        hours (int): The number of hours to check against.

    Returns:
        bool: True if the timestamp is older than the specified number of hours, False otherwise.
    """
    # Get the current UTC time
    now = datetime.now(timezone.utc)
    # Calculate the time threshold
    time_threshold = now - timedelta(hours=hours)

    try:
        # Parse the timestamp and ensure it is timezone-aware
        timestamp_dt = parser.isoparse(date_str)
    except ValueError:
        raise ValueError(f"Invalid timestamp format: {date_str}")

    # Ensure both times are compared in UTC
    if timestamp_dt.tzinfo is None:
        # If the timestamp is naive (has no timezone), assume UTC
        timestamp_dt = timestamp_dt.replace(tzinfo=timezone.utc)

    return timestamp_dt < time_threshold


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


if __name__ == "__main__":
    date_str = "2024-07-29T10:36:02+0700"
    hours_to_check = 1

    if is_date_rfc3339_older_than(date_str, hours_to_check):
        print(f"The timestamp {date_str} is older than {hours_to_check} hour(s).")
    else:
        print(f"The timestamp {date_str} is not older than {hours_to_check} hour(s).")
