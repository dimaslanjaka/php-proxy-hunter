import os
import sqlite3
import sys
from typing import List, Optional, Union

from proxy_hunter import copy_file, delete_path

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.func import get_relative_path
from src.func_console import get_message_exception


class MyDatabaseConnection(sqlite3.Connection):
    def __init__(self, database: str, *args, **kwargs):
        # Initialize the parent class (sqlite3.Connection)
        super().__init__(database, *args, **kwargs)
        self.db_path = database

    # Example of overriding a method to customize behavior
    def execute(self, query: str, parameters=None):
        # print(f"Executing query: {query}")
        # Ensure parameters is either a tuple, list, or dictionary
        if parameters is None:
            parameters = ()
        return super().execute(query, parameters)

    # Example of adding a custom method
    def custom_method(self):
        print("This is a custom method for the database connection.")


class SQLiteHelper:
    """
    A helper class for interacting with SQLite databases.

    Attributes:
        db_path (str): The file path to the SQLite database.
        conn (sqlite3.Connection): The connection object to the SQLite database.
        cursor (sqlite3.Cursor): The cursor object for executing SQL queries.

    Methods:
        create_table(table_name: str, columns: List[str]) -> None:
            Creates a new table in the database if it does not exist.

        insert(table_name: str, data: dict) -> None:
            Inserts data into the specified table.

        select(table_name: str, columns: str = '*', where: Optional[str] = None,
               params: Optional[Union[tuple, list]] = None) -> List[dict]:
            Selects rows from the table based on the given conditions.

        count(table_name: str, where: Optional[str] = None,
              params: Optional[Union[tuple, list]] = None) -> int:
            Returns the number of rows matching the given conditions.

        update(table_name: str, data: dict, where: str,
               params: Optional[Union[tuple, list]] = None) -> None:
            Updates rows in the table that match the given conditions.

        delete(table_name: str, where: str,
               params: Optional[Union[tuple, list]] = None) -> None:
            Deletes rows from the table that match the given conditions.

        execute_query(sql: str) -> None:
            Executes a custom SQL query.

        truncate_table(table_name: str) -> None:
            Deletes all rows from the specified table.

        backup_database(backup_path: str) -> None:
            Creates a backup of the current database.

        dump_database(dump_path: str) -> None:
            Dumps the entire database to a SQL text file.

        create_new_database(new_db_path: str, dump_path: str) -> None:
            Creates a new SQLite database from a SQL dump file.

    Usage:
        # Example:
        >>> db_helper = SQLiteHelper('example.db')
        >>> db_helper.create_table('users', ['id INTEGER PRIMARY KEY', 'name TEXT'])
        >>> db_helper.insert('users', {'name': 'Alice'})
        >>> db_helper.select('users', where='name = ?', params=('Alice',))
        [{'id': 1, 'name': 'Alice'}]
        >>> db_helper.update('users', {'name': 'Bob'}, 'id = ?', (1,))
        >>> db_helper.delete('users', 'name = ?', ('Bob',))
        >>> db_helper.truncate_table('users')
        >>> db_helper.backup_database('backup.db')
        >>> db_helper.dump_database('dump.sql')
        >>> SQLiteHelper.create_new_database('new.db', 'dump.sql')

    Note:
        The class uses the sqlite3 module for database operations. Always ensure to properly
        handle connections using context managers or explicitly closing connections to avoid
        potential resource leaks.
    """

    def __init__(self, db_path: str, check_same_thread=False):
        """
        Initializes a SQLiteHelper instance.

        Args:
            db_path (str): The file path to the SQLite database.
        """
        self.db_path = db_path
        # Connect database
        sqlite3.connect(db_path, check_same_thread=check_same_thread)
        # Wrap custom class
        self.conn = MyDatabaseConnection(db_path, check_same_thread=check_same_thread)
        self.conn.execute("PRAGMA foreign_keys = ON")  # Enable foreign key support
        self.conn.row_factory = sqlite3.Row  # Access rows by column names
        self.cursor = self.conn.cursor()

    def create_table(self, table_name: str, columns: List[str]) -> None:
        """
        Creates a new table in the database if it does not exist.

        Args:
            table_name (str): The name of the table to create.
            columns (List[str]): A list of column names and types formatted as SQL strings.

        Returns:
            None
        """
        columns_str = ", ".join(columns)
        sql = f"CREATE TABLE IF NOT EXISTS {table_name} ({columns_str})"
        self.cursor.execute(sql)
        self.conn.commit()

    def insert(self, table_name: str, data: dict) -> None:
        """
        Inserts data into the specified table.

        Args:
            table_name (str): The name of the table.
            data (dict): A dictionary where keys are column names and values are the values to insert.

        Returns:
            None
        """
        columns = ", ".join(data.keys())
        placeholders = ", ".join("?" * len(data))
        sql = f"INSERT INTO {table_name} ({columns}) VALUES ({placeholders})"
        self.cursor.execute(sql, list(data.values()))
        self.conn.commit()

    def select(
        self,
        table_name: str,
        columns: str = "*",
        where: Optional[str] = None,
        params: Optional[Union[tuple, list]] = None,
        rand: Optional[bool] = False,
    ) -> List[dict]:
        """
        Selects rows from the table based on the given conditions.

        Args:
            table_name (str): The name of the table.
            columns (str): The columns to select (default is '*').
            where (Optional[str]): The WHERE clause without the 'WHERE' keyword (default is None).
            params (Optional[Union[tuple, list]]): Parameters to substitute in the query (default is None).

        Returns:
            List[dict]: A list of dictionaries representing rows, where keys are column names.
        """
        sql = f"SELECT {columns} FROM {table_name}"
        if where:
            sql += f" WHERE {where}"
        if rand:
            sql += " ORDER BY RANDOM()"
        self.cursor.execute(sql, params or ())
        rows = self.cursor.fetchall()
        return [dict(row) for row in rows]

    def count(
        self,
        table_name: str,
        where: Optional[str] = None,
        params: Optional[Union[tuple, list]] = None,
    ) -> int:
        """
        Returns the number of rows matching the given conditions.

        Args:
            table_name (str): The name of the table.
            where (Optional[str]): The WHERE clause without the 'WHERE' keyword (default is None).
            params (Optional[Union[tuple, list]]): Parameters to substitute in the query (default is None).

        Returns:
            int: The number of rows matching the conditions.
        """
        sql = f"SELECT COUNT(*) as count FROM {table_name}"
        if where:
            sql += f" WHERE {where}"
        self.cursor.execute(sql, params or ())
        count = self.cursor.fetchone()["count"]
        return count if count is not None else 0

    def update(
        self,
        table_name: str,
        data: dict,
        where: str,
        params: Optional[Union[tuple, list]] = None,
    ) -> None:
        set_values = ", ".join(f"{key} = ?" for key in data)
        sql = f"UPDATE {table_name} SET {set_values} WHERE {where}"

        # Ensure None values are passed directly, and convert params to a list if necessary
        self.cursor.execute(sql, list(data.values()) + list(params or []))
        self.conn.commit()

    def delete(
        self, table_name: str, where: str, params: Optional[Union[tuple, list]] = None
    ) -> None:
        sql = f"DELETE FROM {table_name} WHERE {where}"
        self.cursor.execute(sql, params or ())
        self.conn.commit()

    def execute_query(self, sql: str):
        self.cursor.execute(sql)
        self.conn.commit()

    def truncate_table(self, table_name: str) -> None:
        sql = f"DELETE FROM {table_name}"
        self.cursor.execute(sql)
        self.conn.commit()

    def backup_database(self, backup_path: str) -> None:
        """
        Creates a backup of the current database.

        Args:
            backup_path (str): The file path where the backup will be saved.

        Returns:
            None
        """
        with sqlite3.connect(backup_path) as backup_conn:
            self.conn.backup(backup_conn)
        print(f"Backup successful to {backup_path}")

    def dump_database(self, dump_path: str) -> None:
        """
        Dumps the entire database to a SQL text file.

        Args:
            dump_path (str): The file path where the SQL dump will be saved.

        Returns:
            None
        """
        with open(dump_path, "w", encoding="utf-8") as f:
            for line in self.conn.iterdump():
                if (
                    line
                    and not line.startswith("BEGIN TRANSACTION")
                    and not line.startswith("COMMIT")
                ):
                    f.write("%s\n" % line)
        print(f"Dump successful to {dump_path}")

    def dump_database_truncate(self, dump_path: str) -> None:
        """
        Dumps the entire database to a SQL text file, truncating tables if they already exist.

        Args:
            dump_path (str): The file path where the SQL dump will be saved.

        Returns:
            None
        """
        with open(dump_path, "w", encoding="utf-8") as f:
            # Get a list of tables in the database
            cursor = self.conn.cursor()
            cursor.execute(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';"
            )
            tables = cursor.fetchall()

            # Iterate over each table
            for table in tables:
                table_name = table[0]

                # Skip the sqlite_sequence table
                if table_name == "sqlite_sequence":
                    continue

                # Write DROP TABLE and CREATE TABLE statements for each table
                cursor.execute(
                    f"SELECT sql FROM sqlite_master WHERE name='{table_name}';"
                )
                create_table_statement = cursor.fetchone()[0]
                f.write(f'DROP TABLE IF EXISTS "{table_name}";\n')
                f.write(f"{create_table_statement};\n")

                # Dump data from the table, excluding the 'id' column
                cursor.execute(f'SELECT * FROM "{table_name}";')
                rows = cursor.fetchall()
                if rows:
                    columns = [
                        description[0]
                        for description in cursor.description
                        if description[0] != "id"
                    ]
                    for row in rows:
                        values = [
                            repr(row[column]) if row[column] is not None else "NULL"
                            for column in columns
                        ]
                        f.write(
                            f"INSERT INTO \"{table_name}\" ({', '.join(columns)}) VALUES ({', '.join(values)});\n"
                        )

        print(f"Dump successful to {dump_path}")

    def create_new_database(self, new_db_path: str, dump_path: str) -> None:
        """
        Creates a new SQLite database from a SQL dump file.

        Args:
            new_db_path (str): The file path for the new SQLite database.
            dump_path (str): The file path to the SQL dump file.

        Returns:
            None
        """
        self.close()
        backup_path = None
        if os.path.exists(new_db_path):
            backup_path = get_relative_path("tmp/sqliteHelper-backup.sqlite")
            copy_file(new_db_path, backup_path)
            delete_path(new_db_path)
        try:
            new_conn = sqlite3.connect(new_db_path)
            with open(dump_path, "r", encoding="utf-8") as f:  # Specify encoding here
                sql_script = f.read()
            new_conn.executescript(sql_script)
            new_conn.close()
            print(f"New database created at {new_db_path} from dump {dump_path}")
        except Exception as e:
            # re-copy original file on error occurs
            if backup_path:
                copy_file(backup_path, new_db_path)
            print(f"Error while creating new database", get_message_exception(e))
        finally:
            # delete backup file
            if backup_path and os.path.exists(backup_path):
                delete_path(backup_path)

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, traceback):
        self.close()

    def close(self):
        self.conn.close()
