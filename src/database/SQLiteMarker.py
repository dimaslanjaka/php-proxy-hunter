import os
import re
import sys
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from typing import Iterable, List, Optional, Set, Union

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from src.SQLiteHelper import SQLiteHelper


@dataclass
class UnseenResult:
    """Result of filtering marker values into seen and unseen items.

    Attributes:
        cleaned: Deduplicated set of all input values (no duplicates).
        pending: Subset of ``cleaned`` that were not found in the marker store.
        already_checked: Number of values already present (and not expired).
    """

    cleaned: Set[str] = field(default_factory=set)
    pending: Set[str] = field(default_factory=set)
    already_checked: int = 0


_IDENTIFIER_RE = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")


class SQLiteMarker:
    """Generic SQLite marker store for idempotent processing across runs."""

    def __init__(
        self,
        db_filename: str,
        table_name: str = "markers",
        key_column: str = "marker",
        base_dir: str = "tmp/database",
    ):
        """Initialize the marker store, creating the backing table if needed.

        Args:
            db_filename: SQLite file name (e.g. ``"my_markers.sqlite"``).
            table_name: Name of the markers table (default ``"markers"``).
            key_column: Column name for the marker value (default ``"marker"``).
            base_dir: Directory relative to project root where the DB is stored.
        """
        self.table_name = self._validate_identifier(table_name)
        self.key_column = self._validate_identifier(key_column)

        db_dir = get_relative_path(base_dir)
        os.makedirs(db_dir, exist_ok=True)

        self.db = SQLiteHelper(os.path.join(db_dir, db_filename))

        self.db.create_table(
            self.table_name,
            [
                f"{self.key_column} TEXT PRIMARY KEY",
                "created_at TEXT NOT NULL",
                "expires_at TEXT",
            ],
        )

        self._ensure_expires_column()
        self._configure_sqlite()

    def _configure_sqlite(self) -> None:
        """Apply performance-oriented SQLite PRAGMAs to the connection."""
        try:
            self.db.execute_query("PRAGMA journal_mode=WAL")
            self.db.execute_query("PRAGMA synchronous=NORMAL")
            self.db.execute_query("PRAGMA temp_store=MEMORY")
            self.db.execute_query("PRAGMA cache_size=-20000")
            self.db.execute_query("PRAGMA busy_timeout=30000")
        except Exception as e:
            print(f"[sqlite] PRAGMA error: {e}")

    def _validate_identifier(self, value: str) -> str:
        """Validate that *value* is a safe SQL identifier and return it.

        Raises:
            ValueError: If *value* is not a valid bare SQL identifier.
        """
        if not _IDENTIFIER_RE.match(value):
            raise ValueError(f"Invalid SQL identifier: {value}")
        return value

    def _ensure_expires_column(self) -> None:
        """Add the ``expires_at`` column if it does not exist (schema migration)."""
        if not self.db.column_exists(self.table_name, "expires_at"):
            self.db.execute_query(
                f"ALTER TABLE {self.table_name} ADD COLUMN expires_at TEXT"
            )

    def _normalize_rfc3339(self, value: str) -> str:
        """Parse *value* as an RFC3339 timestamp and return a normalized, UTC-aware ISO 8601 string.

        Handles ``Z`` suffix and naive datetimes by assuming UTC.

        Raises:
            ValueError: If *value* is empty or cannot be parsed.
        """
        text = str(value).strip()
        if not text:
            raise ValueError("RFC3339 value is required")

        if text.endswith("Z"):
            text = f"{text[:-1]}+00:00"

        parsed = datetime.fromisoformat(text)

        if parsed.tzinfo is None:
            parsed = parsed.replace(tzinfo=timezone.utc)

        return parsed.astimezone(timezone.utc).isoformat()

    def get_existing(
        self, values: Iterable[str], as_of: Optional[str] = None
    ) -> Set[str]:
        """Return the subset of values that already exist in the marker table.

        Args:
            values: Candidate marker values to check.
            as_of: Optional RFC3339 timestamp used to ignore expired markers.

        Returns:
            A set containing the values that are already present and not expired.
        """
        normalized = [str(v).strip() for v in values if str(v).strip()]

        if not normalized:
            return set()

        as_of_value = (
            self._normalize_rfc3339(as_of)
            if as_of
            else datetime.now(timezone.utc).isoformat()
        )

        CHUNK_SIZE = 900
        existing: Set[str] = set()

        for i in range(0, len(normalized), CHUNK_SIZE):
            chunk = normalized[i : i + CHUNK_SIZE]
            placeholders = ",".join("?" for _ in chunk)
            sql = (
                f"SELECT {self.key_column} FROM {self.table_name} "
                f"WHERE {self.key_column} IN ({placeholders}) "
                f"AND (expires_at IS NULL OR expires_at > ?)"
            )
            rows = self.db.execute_query_fetch(sql, [*chunk, as_of_value])
            if isinstance(rows, list):
                existing.update(
                    str(r.get(self.key_column))
                    for r in rows
                    if isinstance(r, dict) and r.get(self.key_column)
                )

        return existing

    def filter_unseen(
        self, values: Iterable[str], as_of: Optional[str] = None
    ) -> UnseenResult:
        """Deduplicate values and split them into cleaned, pending, and seen items.

        Args:
            values: Candidate marker values to filter.
            as_of: Optional RFC3339 timestamp used to ignore expired markers.

        Returns:
            An ``UnseenResult`` containing the deduplicated input, unseen values,
            and the number of already existing values.
        """
        cleaned: Set[str] = set()
        ordered: Set[str] = set()

        for v in values:
            v = str(v).strip()
            if not v or v in cleaned:
                continue
            cleaned.add(v)
            ordered.add(v)

        if not ordered:
            return UnseenResult()

        existing = self.get_existing(ordered, as_of)
        pending = ordered - existing

        return UnseenResult(
            cleaned=cleaned, pending=pending, already_checked=len(existing)
        )

    def _resolve_valid_until(
        self, valid_until: Optional[Union[str, int]]
    ) -> Optional[str]:
        """Convert *valid_until* into a UTC ISO 8601 string or ``None``.

        An integer is interpreted as days from now. A string must be an
        RFC3339-compatible timestamp (see :meth:`_normalize_rfc3339`).

        Returns:
            The computed expiry timestamp, or ``None`` if no expiry was set.
        """
        if valid_until is None:
            return None

        if isinstance(valid_until, int):
            return (
                datetime.now(timezone.utc) + timedelta(days=valid_until)
            ).isoformat()

        return self._normalize_rfc3339(valid_until)

    def mark(self, value: str, valid_until: Optional[Union[str, int]] = None) -> None:
        """Record *value* in the marker store with an optional expiry.

        If the value already exists its ``created_at`` and ``expires_at``
        are updated. This is an upsert — no error is raised on duplicates.

        Args:
            value: The marker value (e.g. a proxy string).
            valid_until: Either an integer for days from now, or an RFC3339
                timestamp string. ``None`` (default) means never expire.
        """
        value = str(value).strip()
        if not value:
            return

        now = datetime.now(timezone.utc).isoformat()
        expires = self._resolve_valid_until(valid_until)

        self.db.execute_query(
            f"""
            UPDATE {self.table_name}
            SET created_at = ?, expires_at = ?
            WHERE {self.key_column} = ?
            """,
            [now, expires, value],
        )

        self.db.execute_query(
            f"""
            INSERT OR IGNORE INTO {self.table_name}
            ({self.key_column}, created_at, expires_at)
            VALUES (?, ?, ?)
            """,
            [value, now, expires],
        )

    def close(self):
        """Close the underlying SQLite database connection."""
        self.db.close()

    def __enter__(self):
        """Enter runtime context and return self."""
        return self

    def __exit__(self, *_):
        """Exit runtime context and close the database connection."""
        self.close()
