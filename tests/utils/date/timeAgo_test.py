from datetime import datetime, timedelta

import pytz
import os
import sys
import pytest

# Ensure repository root is on sys.path so `src` package can be imported
root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
if root not in sys.path:
    sys.path.insert(0, root)

from src.utils.date.timeAgo import time_ago


def test_none_and_empty_return_dash():
    assert time_ago(None) == "-"
    assert time_ago("") == "-"


def test_seconds_ago_contains_seconds_and_ago():
    d = datetime.now(pytz.UTC) - timedelta(seconds=45)
    res = time_ago(d)
    assert res.endswith(" ago")
    assert "second" in res


def test_hours_and_minutes_ago():
    d = datetime.now(pytz.UTC) - timedelta(hours=2, minutes=15)
    res = time_ago(d)
    assert res.endswith(" ago")
    assert res.startswith("2 hour") or res.startswith("2 hours")
    assert "15 minute" in res


def test_days_and_hours_ago():
    d = datetime.now(pytz.UTC) - timedelta(days=1, hours=3)
    res = time_ago(d)
    assert res.endswith(" ago")
    assert "1 day" in res
    assert "3 hour" in res


if __name__ == "__main__":
    # Run this test module directly. Exit with pytest's return code.
    pytest.main(["-vvv", "-s", __file__])
