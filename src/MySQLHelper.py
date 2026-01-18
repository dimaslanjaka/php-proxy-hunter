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
        use_limit_param = limit is not None
        if use_limit_param:
            sql += " LIMIT %s"

        exec_params_list = list(params) if params is not None else []
        if use_limit_param:
            exec_params_list.append(limit)

        exec_params = tuple(exec_params_list)
        self.cursor.execute(sql, exec_params)
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

    def execute_query_fetch(
        self, sql: str, params: Optional[Union[tuple, list]] = None
    ) -> Union[List[Dict[str, Any]], int]:
        """
        Executes a custom SQL query and returns results when available.

        - For SELECT-like queries returns a list of dictionaries (column->value).
        - For non-SELECT queries returns the integer affected row count.
        """
        cur = self.conn.cursor(dictionary=True)
        try:
            if params:
                cur.execute(sql, params)
            else:
                cur.execute(sql)

            # If cursor.description is populated, there are rows to fetch
            if cur.description:
                rows = cur.fetchall()
                # rows are dicts because cursor was created with dictionary=True
                return cast(List[Dict[str, Any]], rows)

            # No resultset: commit and return affected rowcount
            self.conn.commit()
            return cur.rowcount
        finally:
            cur.close()

    def column_exists(self, table_name: str, column_name: str) -> bool:
        """
        Check whether a column exists in a given table for MySQL.

        Uses information_schema.columns to determine existence.
        """
        db_name = self.mysql_database or getattr(self.conn, "database", None)
        if not db_name:
            return False
        sql = (
            "SELECT COUNT(*) AS cnt FROM information_schema.columns "
            "WHERE table_schema = %s AND table_name = %s AND column_name = %s"
        )
        self.cursor.execute(sql, (db_name, table_name, column_name))
        res = self.cursor.fetchone()
        if not res:
            return False
        # fetchone() returns a dict because cursor was created with dictionary=True
        try:
            cnt = res.get("cnt") if isinstance(res, dict) else res[0]
            return int(cast(Any, cnt)) > 0
        except Exception:
            return False

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
