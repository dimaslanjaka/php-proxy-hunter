from typing import Optional, Union, Any
from datetime import datetime, timedelta
import pytz
from dateutil.parser import parse as _parse_date


def time_ago(
    value: Optional[Union[str, datetime]] = None, tz: Optional[Union[str, Any]] = None
) -> str:
    """
    Convert a datetime or ISO-like date string to a human-readable "time ago" string.

    Accepts either a `datetime` or a string. If a string can't be parsed with
    `datetime.fromisoformat`, falls back to `dateutil.parser.parse` if available.

        Optional `tz` parameter controls timezone handling for naive datetimes:
        - If `tz` is None and `value` is naive, uses a naive `now()` (no timezone).
        - If `tz` is a string, it is passed to `pytz.timezone()` and used to
            localize naive datetimes.
        - If `tz` is a tzinfo-like object, it will be used to localize naive datetimes.

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

    # Framework-agnostic timezone handling.
    # If the parsed `date` is naive, use the optional `tz` argument to
    # localize it; otherwise, keep it as-is and compute `now` with the same
    # tz-awareness as `date`.
    if date.tzinfo is None:
        if tz is None:
            # keep naive: compare with naive now
            now = datetime.now()
        else:
            # resolve tz parameter to a tzinfo
            if isinstance(tz, str):
                tzinfo = pytz.timezone(tz)
            else:
                tzinfo = tz

            # localize naive date to tzinfo
            try:
                if hasattr(tzinfo, "localize"):
                    date = tzinfo.localize(date)
                else:
                    date = date.replace(tzinfo=tzinfo)
            except Exception:
                date = date.replace(tzinfo=tzinfo)

            # now in same tz
            try:
                now = datetime.now(tzinfo)
            except Exception:
                now = datetime.now(pytz.UTC).astimezone(tzinfo)
    else:
        # aware datetime: compute now in the same timezone
        try:
            now = datetime.now(date.tzinfo)
        except Exception:
            now = datetime.now(pytz.UTC).astimezone(date.tzinfo)

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
