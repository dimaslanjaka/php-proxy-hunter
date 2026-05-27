import os
import sys
from pathlib import Path
import pytest

# Add project root to Python path for direct imports from src/
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..")))

from src.database.SQLiteMarker import SQLiteMarker


def _new_marker(tmp_path: Path) -> SQLiteMarker:
    return SQLiteMarker(
        db_filename="test_sqlite_marker.sqlite",
        table_name="checked_proxies",
        key_column="proxy",
        base_dir=str(tmp_path),
    )


@pytest.fixture
def marker(tmp_path: Path):
    instance = _new_marker(tmp_path)
    yield instance
    instance.close()


def test_mark_without_expiry_is_always_valid(marker: SQLiteMarker) -> None:
    marker.mark("1.1.1.1:80")

    cleaned, pending, already_checked = marker.filter_unseen(
        ["1.1.1.1:80", "2.2.2.2:8080"],
        as_of="2030-01-01T00:00:00Z",
    )

    assert cleaned == {"1.1.1.1:80", "2.2.2.2:8080"}
    assert already_checked == 1
    assert pending == ["2.2.2.2:8080"]


def test_expired_marker_is_treated_as_unseen(marker: SQLiteMarker) -> None:
    marker.mark("3.3.3.3:1080", valid_until="2025-01-01T00:00:00Z")

    cleaned, pending, already_checked = marker.filter_unseen(
        ["3.3.3.3:1080"],
        as_of="2026-01-01T00:00:00Z",
    )

    assert cleaned == {"3.3.3.3:1080"}
    assert already_checked == 0
    assert pending == ["3.3.3.3:1080"]


def test_future_expiry_is_respected_with_as_of(marker: SQLiteMarker) -> None:
    marker.mark("4.4.4.4:3128", valid_until="2027-01-01T00:00:00Z")

    cleaned_before, pending_before, checked_before = marker.filter_unseen(
        ["4.4.4.4:3128"],
        as_of="2026-01-01T00:00:00Z",
    )
    assert cleaned_before == {"4.4.4.4:3128"}
    assert checked_before == 1
    assert pending_before == []

    cleaned_after, pending_after, checked_after = marker.filter_unseen(
        ["4.4.4.4:3128"],
        as_of="2028-01-01T00:00:00Z",
    )
    assert cleaned_after == {"4.4.4.4:3128"}
    assert checked_after == 0
    assert pending_after == ["4.4.4.4:3128"]


def test_mark_refreshes_existing_expiry(marker: SQLiteMarker) -> None:
    marker.mark("5.5.5.5:9050", valid_until="2025-01-01T00:00:00Z")
    marker.mark("5.5.5.5:9050", valid_until="2030-01-01T00:00:00Z")

    cleaned, pending, already_checked = marker.filter_unseen(
        ["5.5.5.5:9050"],
        as_of="2026-01-01T00:00:00Z",
    )

    assert cleaned == {"5.5.5.5:9050"}
    assert already_checked == 1
    assert pending == []


def test_mark_supports_days_from_now(marker: SQLiteMarker) -> None:
    marker.mark("6.6.6.6:8080", 30)

    cleaned_now, pending_now, checked_now = marker.filter_unseen(
        ["6.6.6.6:8080"],
        as_of="2026-04-15T00:00:00Z",
    )
    assert cleaned_now == {"6.6.6.6:8080"}
    assert checked_now == 1
    assert pending_now == []

    cleaned_later, pending_later, checked_later = marker.filter_unseen(
        ["6.6.6.6:8080"],
        as_of="2026-07-01T00:00:00Z",
    )
    assert cleaned_later == {"6.6.6.6:8080"}
    assert checked_later == 0
    assert pending_later == ["6.6.6.6:8080"]


if __name__ == "__main__":
    pytest.main(["-vvv", "-s", __file__])
