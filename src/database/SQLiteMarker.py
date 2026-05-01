import os
import re
from datetime import datetime, timedelta, timezone
from typing import Iterable, List, Optional, Set, Tuple, Union

from src.SQLiteHelper import SQLiteHelper
from src.func import get_relative_path

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

    def _validate_identifier(self, value: str) -> str:
        if not _IDENTIFIER_RE.match(value):
            raise ValueError(f"Invalid SQL identifier: {value}")
        return value

    def _ensure_expires_column(self) -> None:
        """Backfill expires_at column for marker DBs created before expiration support."""
        if not self.db.column_exists(self.table_name, "expires_at"):
            self.db.execute_query(
                f"ALTER TABLE {self.table_name} ADD COLUMN expires_at TEXT"
            )

    def _normalize_rfc3339(self, value: str) -> str:
        text = str(value).strip()
        if not text:
            raise ValueError("RFC3339 value is required")

        if text.endswith("Z"):
            text = f"{text[:-1]}+00:00"

        parsed = datetime.fromisoformat(text)
        if parsed.tzinfo is None:
            parsed = parsed.replace(tzinfo=timezone.utc)

        # Store normalized UTC RFC3339 for stable comparisons.
        return parsed.astimezone(timezone.utc).isoformat()

    def get_existing(
        self, values: Iterable[str], as_of: Optional[str] = None
    ) -> Set[str]:
        normalized = [str(value).strip() for value in values if str(value).strip()]
        if not normalized:
            return set()

        as_of_value = (
            self._normalize_rfc3339(as_of)
            if as_of
            else datetime.now(timezone.utc).isoformat()
        )

        placeholders = ",".join("?" for _ in normalized)
        sql = (
            f"SELECT {self.key_column} FROM {self.table_name} "
            f"WHERE {self.key_column} IN ({placeholders}) "
            f"AND (expires_at IS NULL OR expires_at > ?)"
        )

        rows = self.db.execute_query_fetch(sql, [*normalized, as_of_value])
        rows_list = rows if isinstance(rows, list) else []
        return {
            str(row.get(self.key_column))
            for row in rows_list
            if isinstance(row, dict) and row.get(self.key_column)
        }

    def filter_unseen(
        self, values: Iterable[str], as_of: Optional[str] = None
    ) -> Tuple[List[str], int]:
        """Return unseen values that are not marked as valid at ``as_of``.

        Args:
            values: Input marker values to deduplicate and inspect.
            as_of: RFC3339 timestamp used to evaluate expiration. Defaults to now.

        Returns:
            A tuple of ``(pending_values, already_checked_count)`` where
            ``pending_values`` preserves input order and excludes valid markers.
        """
        cleaned: List[str] = []
        seen_in_input: Set[str] = set()

        for value in values:
            marker = str(value).strip()
            if not marker or marker in seen_in_input:
                continue
            seen_in_input.add(marker)
            cleaned.append(marker)

        if not cleaned:
            return [], 0

        existing = self.get_existing(cleaned, as_of=as_of)
        pending = [value for value in cleaned if value not in existing]
        return pending, len(existing)

    def _resolve_valid_until(
        self, valid_until: Optional[Union[str, int]]
    ) -> Optional[str]:
        if valid_until is None:
            return None

        if isinstance(valid_until, int):
            return (
                datetime.now(timezone.utc) + timedelta(days=valid_until)
            ).isoformat()

        return self._normalize_rfc3339(valid_until)

    def mark(self, value: str, valid_until: Optional[Union[str, int]] = None) -> None:
        """Persist a marker and optionally set its expiration.

        Args:
            value: Marker value to store.
            valid_until: Expiration control for the marker.
                - ``None`` keeps the marker valid indefinitely.
                - ``int`` is treated as a number of days from now.
                - ``str`` must be an RFC3339 timestamp.
        """
        marker = str(value).strip()
        if not marker:
            return

        created_at = datetime.now(timezone.utc).isoformat()
        expires_at = self._resolve_valid_until(valid_until)

        # Update first so existing markers can refresh expiration.
        self.db.execute_query(
            f"""
            UPDATE {self.table_name}
            SET created_at = ?, expires_at = ?
            WHERE {self.key_column} = ?
            """,
            [created_at, expires_at, marker],
        )

        # Insert path for first mark.
        self.db.execute_query(
            f"""
            INSERT OR IGNORE INTO {self.table_name} ({self.key_column}, created_at, expires_at)
            VALUES (?, ?, ?)
            """,
            [marker, created_at, expires_at],
        )

    def close(self) -> None:
        self.db.close()

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        self.close()
