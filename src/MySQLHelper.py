import mysql.connector
from mysql.connector import Error
from typing import Any, List, Optional, Union, Dict, Sequence, cast


class MySQLConnection(mysql.connector.connection.MySQLConnection):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)

    def custom_method(self):
        print("This is a custom MySQL connection method.")


class MySQLHelper:
    """
    A helper class for interacting with MySQL databases.

    Example:
        >>> db = MySQLHelper(host="localhost", user="root", password="1234", database="testdb")
        >>> db.create_table("users", ["id INT AUTO_INCREMENT PRIMARY KEY", "name VARCHAR(255)"])
        >>> db.insert("users", {"name": "Alice"})
        >>> db.select("users", where="name=%s", params=("Alice",))
    """

    def __init__(
        self,
        host: str = "localhost",
        user: str = "root",
        password: str = "",
        database: Optional[str] = None,
        port: int = 3306,
        autocommit: bool = True,
    ):
        self.conn = MySQLConnection(
            host=host,
            user=user,
            password=password,
            database=database,
            port=port,
        )
        self.conn.autocommit = autocommit
        self.cursor = self.conn.cursor(dictionary=True)
        self.mysql_version = self.conn.get_server_info()
        self.mysql_username = user
        self.mysql_password = password
        self.mysql_host = host
        self.mysql_port = port
        self.mysql_database = database

    # ---------- Core CRUD ----------

    def create_table(self, table_name: str, columns: List[str]) -> None:
        columns_str = ", ".join(columns)
        sql = f"CREATE TABLE IF NOT EXISTS {table_name} ({columns_str})"
        self.cursor.execute(sql)

    def insert(self, table_name: str, data: Dict[str, Any]) -> None:
        columns = ", ".join(data.keys())
        placeholders = ", ".join(["%s"] * len(data))
        sql = f"INSERT INTO {table_name} ({columns}) VALUES ({placeholders})"
        self.cursor.execute(sql, tuple(data.values()))
        self.conn.commit()

    def select(
        self,
        table_name: str,
        columns: str = "*",
        where: Optional[str] = None,
        params: Optional[Union[tuple, list]] = None,
        rand: Optional[bool] = False,
        limit: Optional[int] = None,
    ) -> Sequence[Dict[str, Any]]:
        sql = f"SELECT {columns} FROM {table_name}"
        if where:
            sql += f" WHERE {where}"
        if rand:
            sql += " ORDER BY RAND()"
        if limit:
            sql += f" LIMIT {limit}"
        self.cursor.execute(sql, params or ())
        rows = self.cursor.fetchall()
        return cast(Sequence[Dict[str, Any]], rows)

    def count(
        self,
        table_name: str,
        where: Optional[str] = None,
        params: Optional[Union[tuple, list]] = None,
    ) -> int:
        sql = f"SELECT COUNT(*) AS count FROM {table_name}"
        if where:
            sql += f" WHERE {where}"
        self.cursor.execute(sql, params or ())
        result = self.cursor.fetchone()
        if not result:
            return 0
        # `fetchone()` may return a dict (with `dictionary=True`) or a sequence/tuple.
        if isinstance(result, dict):
            val: Any = result.get("count")
            try:
                return int(val) if val is not None else 0
            except Exception:
                return 0
        # fallback: assume first column is the count; coerce safely
        try:
            first: Any = result[0] if result and len(result) > 0 else None
            return int(first) if first is not None else 0
        except Exception:
            return 0

    def update(
        self,
        table_name: str,
        data: Dict[str, Any],
        where: str,
        params: Optional[Union[tuple, list]] = None,
    ) -> None:
        set_values = ", ".join(f"{key}=%s" for key in data)
        sql = f"UPDATE {table_name} SET {set_values} WHERE {where}"
        values = list(data.values()) + list(params or [])
        self.cursor.execute(sql, values)
        self.conn.commit()

    def delete(
        self,
        table_name: str,
        where: str,
        params: Optional[Union[tuple, list]] = None,
    ) -> None:
        sql = f"DELETE FROM {table_name} WHERE {where}"
        self.cursor.execute(sql, params or ())
        self.conn.commit()

    # ---------- Utility Methods ----------

    def execute_query(
        self, sql: str, params: Optional[Union[tuple, list]] = None
    ) -> None:
        if params:
            self.cursor.execute(sql, params)
        else:
            self.cursor.execute(sql)
        self.conn.commit()

    def truncate_table(self, table_name: str) -> None:
        sql = f"TRUNCATE TABLE {table_name}"
        self.cursor.execute(sql)
        self.conn.commit()

    def dump_database(self, dump_path: str) -> None:
        """
        Exports the current database schema and data to an SQL dump file.
        Requires `mysqldump` to be available in the system PATH.
        """
        import os
        import subprocess

        if not self.conn.database:
            raise ValueError("No database selected for dump.")

        cmd = [
            "mysqldump",
            f"--host={self.mysql_host}",
            f"--user={self.mysql_username}",
            f"--password={self.mysql_password}",
            self.mysql_database,
        ]
        with open(dump_path, "w", encoding="utf-8") as f:
            subprocess.run(cmd, stdout=f, check=True)
        print(f"Dump successful to {dump_path}")

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        self.close()

    def close(self):
        if self.cursor:
            self.cursor.close()
        if self.conn:
            self.conn.close()
