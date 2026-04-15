import os
import re
from typing import Iterable, List, Set, Tuple

from src.SQLiteHelper import SQLiteHelper
from src.func import get_relative_path
from src.func_date import get_current_rfc3339_time


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
            ],
        )

    def _validate_identifier(self, value: str) -> str:
        if not _IDENTIFIER_RE.match(value):
            raise ValueError(f"Invalid SQL identifier: {value}")
        return value

    def get_existing(self, values: Iterable[str]) -> Set[str]:
        normalized = [str(value).strip() for value in values if str(value).strip()]
        if not normalized:
            return set()

        placeholders = ",".join("?" for _ in normalized)
        sql = (
            f"SELECT {self.key_column} FROM {self.table_name} "
            f"WHERE {self.key_column} IN ({placeholders})"
        )

        rows = self.db.execute_query_fetch(sql, normalized)
        rows_list = rows if isinstance(rows, list) else []
        return {
            str(row.get(self.key_column))
            for row in rows_list
            if isinstance(row, dict) and row.get(self.key_column)
        }

    def filter_unseen(self, values: Iterable[str]) -> Tuple[List[str], int]:
        """Return unique unseen values while preserving order, plus skipped count."""
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

        existing = self.get_existing(cleaned)
        pending = [value for value in cleaned if value not in existing]
        return pending, len(existing)

    def mark(self, value: str) -> None:
        marker = str(value).strip()
        if not marker:
            return

        self.db.execute_query(
            f"""
            INSERT OR IGNORE INTO {self.table_name} ({self.key_column}, created_at)
            VALUES (?, ?)
            """,
            [marker, get_current_rfc3339_time()],
        )

    def close(self) -> None:
        self.db.close()

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        self.close()
