import sys
from pathlib import Path
from PySide6.QtSql import QSqlDatabase, QSqlQuery
from typing import Optional
import os

# Ensure repository root is on sys.path so imports work
_REPO_ROOT = str(Path(__file__).resolve().parent.parent.parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

from src.func import get_relative_path


def _get_db_path() -> str:
    """Get or create the database path in app data directory."""
    app_dir = get_relative_path(".data")
    os.makedirs(app_dir, exist_ok=True)
    return os.path.join(app_dir, "app_settings.db")


def _init_db() -> bool:
    """Initialize the settings database if it doesn't exist."""
    try:
        db_path = _get_db_path()
        db = QSqlDatabase.addDatabase("QSQLITE", "settings_conn")
        db.setDatabaseName(db_path)

        if not db.open():
            return False

        # Create settings table if it doesn't exist
        query = QSqlQuery(db)
        query.exec(
            "CREATE TABLE IF NOT EXISTS settings " "(key TEXT PRIMARY KEY, value TEXT)"
        )

        return True
    except Exception:
        return False


def save_text(key: str, text: str) -> None:
    """Save a string value under the given key using SQLite."""
    try:
        db = QSqlDatabase.database("settings_conn")
        if not db.isOpen():
            _init_db()
            db = QSqlDatabase.database("settings_conn")

        query = QSqlQuery(db)
        # Use INSERT OR REPLACE to handle both insert and update
        query.prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")
        query.addBindValue(key)
        query.addBindValue(text)
        query.exec()
    except Exception:
        # Silently ignore failures to avoid crashing UI callers.
        return


def load_text(key: str) -> Optional[str]:
    """Load a string value for the given key from SQLite. Returns None if not found or on error."""
    try:
        db = QSqlDatabase.database("settings_conn")
        if not db.isOpen():
            _init_db()
            db = QSqlDatabase.database("settings_conn")

        query = QSqlQuery(db)
        query.prepare("SELECT value FROM settings WHERE key = ?")
        query.addBindValue(key)

        if query.exec() and query.next():
            return query.value(0)

        return None
    except Exception:
        return None
