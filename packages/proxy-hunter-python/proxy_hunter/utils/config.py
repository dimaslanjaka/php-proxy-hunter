import sqlite3
import json
from typing import Any, Optional, Type, TypeVar

try:
    import mysql.connector
except ImportError:
    mysql = None

T = TypeVar("T")


class ConfigDB:
    def __init__(self, driver: str = "sqlite", **kwargs):
        """Unified config database supporting SQLite and MySQL."""
        self.driver = driver.lower()

        if self.driver == "sqlite":
            self.conn = sqlite3.connect(kwargs.get("db_path", "config.db"))
            self.conn.row_factory = sqlite3.Row
            self.cur = self.conn.cursor()
        elif self.driver == "mysql":
            if mysql is None:
                raise ImportError("Install mysql-connector-python for MySQL support")
            self.conn = mysql.connector.connect(
                host=kwargs.get("host", "localhost"),
                user=kwargs.get("user", "root"),
                password=kwargs.get("password", ""),
                database=kwargs.get("database", "test"),
            )
            self.cur = self.conn.cursor(dictionary=True)
        else:
            raise ValueError("Unsupported driver. Use 'sqlite' or 'mysql'.")

        self._create_table()

    # --- internal ---
    def _create_table(self):
        sql = {
            "sqlite": """
                CREATE TABLE IF NOT EXISTS config (
                    name TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                )
            """,
            "mysql": """
                CREATE TABLE IF NOT EXISTS config (
                    name VARCHAR(255) PRIMARY KEY,
                    value TEXT NOT NULL
                )
            """,
        }
        self.cur.execute(sql[self.driver])
        self.conn.commit()

    def _encode(self, value: Any) -> str:
        from dataclasses import asdict, is_dataclass

        if is_dataclass(value):
            return json.dumps(asdict(value))
        try:
            return json.dumps(value)
        except Exception:
            return str(value)

    def _decode(self, value: str) -> Any:
        try:
            return json.loads(value)
        except Exception:
            return value

    # --- simplified public API ---
    def set(self, key: str, value: Any):
        """Create or update a config entry."""
        encoded = self._encode(value)
        if self.driver == "sqlite":
            query = "INSERT OR REPLACE INTO config (name, value) VALUES (?, ?)"
        else:
            query = """
                INSERT INTO config (name, value) VALUES (%s, %s)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            """
        self.cur.execute(query, (key, encoded))
        self.conn.commit()

    def get(self, key: str, model: Optional[Type[T]] = None) -> Optional[Any]:
        """Retrieve config value; auto-convert JSON and dataclasses."""
        query = (
            "SELECT value FROM config WHERE name = ?"
            if self.driver == "sqlite"
            else "SELECT value FROM config WHERE name = %s"
        )
        self.cur.execute(query, (key,))
        row = self.cur.fetchone()
        if not row:
            return None

        raw = row["value"] if self.driver == "mysql" else row["value"]
        decoded = self._decode(raw)

        # reconstruct dataclass if model is provided
        if model and isinstance(decoded, dict):
            try:
                return model(**decoded)
            except Exception:
                pass
        return decoded

    def delete(self, key: str):
        """Delete config entry by key."""
        query = (
            "DELETE FROM config WHERE name = ?"
            if self.driver == "sqlite"
            else "DELETE FROM config WHERE name = %s"
        )
        self.cur.execute(query, (key,))
        self.conn.commit()

    def close(self):
        self.cur.close()
        self.conn.close()

    def __del__(self):
        self.close()
