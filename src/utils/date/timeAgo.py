from typing import Optional, Union
from datetime import datetime, timedelta

import pytz

try:
    from dateutil.parser import parse as _parse_date
except Exception:  # pragma: no cover - dateutil may not be available in some contexts
    _parse_date = None


def time_ago(value: Optional[Union[str, datetime]] = None) -> str:
    """
    Convert a datetime or ISO-like date string to a human-readable "time ago" string.

    Accepts either a `datetime` or a string. If a string can't be parsed with
    `datetime.fromisoformat`, falls back to `dateutil.parser.parse` if available.

    Returns '-' when `value` is falsy.
    """
    if not value:
        return "-"

    # If already a datetime, use it
    if isinstance(value, datetime):
        date = value
    else:
        # Try ISO format first (fast path)
        try:
            date = datetime.fromisoformat(str(value))
        except Exception:
            if _parse_date:
                try:
                    date = _parse_date(str(value))
                except Exception:
                    return str(value)
            else:
                return str(value)

    # Try to use Django timezone/settings if available, otherwise fall back to UTC
    # Only use Django timezone/settings when Django is installed and configured.
    try:
        from django.conf import settings as _dj_settings  # type: ignore
    except Exception:
        _dj_settings = None

    settings_configured = bool(getattr(_dj_settings, "configured", False))

    if settings_configured:
        try:
            from django.utils import timezone as _dj_timezone  # type: ignore
        except Exception:
            _dj_timezone = None
    else:
        _dj_timezone = None

    # If date is naive (no tz), assume Django TIME_ZONE when available, else UTC
    if date.tzinfo is None:
        tz_name = (
            getattr(_dj_settings, "TIME_ZONE", "UTC") if settings_configured else "UTC"
        )
        local_tz = pytz.timezone(tz_name)
        date = local_tz.localize(date)

    now = (
        _dj_timezone.now()
        if (_dj_timezone and settings_configured)
        else datetime.now(pytz.UTC)
    )
    diff = now - date

    seconds = diff.total_seconds()
    minutes = seconds // 60
    hours = minutes // 60
    days = hours // 24

    remaining_hours = hours % 24
    remaining_minutes = minutes % 60
    remaining_seconds = seconds % 60

    parts = []
    if days > 0:
        parts.append(f"{int(days)} day{'s' if days != 1 else ''}")
        if remaining_hours > 0:
            parts.append(
                f"{int(remaining_hours)} hour{'s' if remaining_hours != 1 else ''}"
            )
    elif remaining_hours > 0:
        parts.append(
            f"{int(remaining_hours)} hour{'s' if remaining_hours != 1 else ''}"
        )
        if remaining_minutes > 0:
            parts.append(
                f"{int(remaining_minutes)} minute{'s' if remaining_minutes != 1 else ''}"
            )
    elif remaining_minutes > 0:
        parts.append(
            f"{int(remaining_minutes)} minute{'s' if remaining_minutes != 1 else ''}"
        )
        if remaining_seconds > 0:
            parts.append(
                f"{int(remaining_seconds)} second{'s' if remaining_seconds != 1 else ''}"
            )
    elif remaining_seconds > 0:
        parts.append(
            f"{int(remaining_seconds)} second{'s' if remaining_seconds != 1 else ''}"
        )

    if not parts:
        return "just now"

    return " ".join(parts) + " ago"


if __name__ == "__main__":
    # Simple test cases (standalone, without Django configured)
    from time import sleep

    print(time_ago(None))  # "-"
    print(time_ago(""))  # "-"
    print(time_ago("2024-01-01T12:00:00+00:00"))  # e.g. "X days ago"
    print(time_ago(datetime.now(pytz.UTC) - timedelta(seconds=45)))  # "45 seconds ago"
    print(
        time_ago(datetime.now(pytz.UTC) - timedelta(minutes=5, seconds=30))
    )  # "5 minutes 30 seconds ago"
    print(
        time_ago(datetime.now(pytz.UTC) - timedelta(hours=2, minutes=15))
    )  # "2 hours 15 minutes ago"
    print(
        time_ago(datetime.now(pytz.UTC) - timedelta(days=1, hours=3))
    )  # "1 day 3 hours ago"
    sleep(2)
    print(time_ago(datetime.now(pytz.UTC) - timedelta(seconds=2)))  # "2 seconds ago"
