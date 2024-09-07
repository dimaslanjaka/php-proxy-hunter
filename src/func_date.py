import os
import sys
from datetime import datetime, timedelta, timezone
from typing import Optional

from dateutil import parser
from tzlocal import get_localzone

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.func_console import log_error


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


def convert_rfc3339_to_human_readable(rfc3339_date: str):
    """
    Convert DATE_RFC3339 string to human readable format
    """
    # Remove trailing 'Z' if present (Z denotes UTC time zone)
    if rfc3339_date.endswith("Z"):
        rfc3339_date = rfc3339_date[:-1]

    # Convert RFC 3339 formatted date string to datetime object
    dt = datetime.fromisoformat(rfc3339_date)

    # Format datetime object as human-readable date and time
    return dt.strftime("%Y-%m-%d %H:%M:%S")


def get_system_timezone() -> str:
    """
    Get system timezone name.
    """
    test = get_localzone()
    if not test:
        return "UTC"
    return str(test.key)


def is_date_rfc3339_hour_more_than(
    date_string: Optional[str], hours: int
) -> Optional[bool]:
    """
    Check if the given date string is more than the specified hours ago.

    Args:
    - date_string (str): The date string in RFC3339 format (e.g., "2024-05-06T12:34:56+00:00").
    - hours (int): The number of hours.

    Returns:
    - bool: True if the date is more than the specified hours ago, False otherwise.
    """
    if not date_string:
        return None

    try:
        # Parse the date string and calculate the time difference in one step
        date_time = datetime.fromisoformat(date_string)
        return (datetime.now(timezone.utc) - date_time) >= timedelta(hours=hours)

    except ValueError:
        # Log error instead of raising an exception
        log_error(
            f"Invalid date string format: {date_string}. Expected RFC3339 format."
        )
        return None


if __name__ == "__main__":
    date_str = "2024-07-29T10:36:02+0700"
    hours_to_check = 1

    if is_date_rfc3339_older_than(date_str, hours_to_check):
        print(f"The timestamp {date_str} is older than {hours_to_check} hour(s).")
    else:
        print(f"The timestamp {date_str} is not older than {hours_to_check} hour(s).")
