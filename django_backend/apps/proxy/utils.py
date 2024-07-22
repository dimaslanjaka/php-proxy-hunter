import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

import sqlite3

from django.db import connection
from proxy_checker import *
from proxy_hunter import *

from src.func_proxy import *
from src.geoPlugin import *
from src.ProxyDB import ProxyDB


def get_db_connections() -> List[sqlite3.Connection]:
    """
    Attempts to retrieve database connections.

    This function tries to create a connection to a SQLite database
    using the ProxyDB class. If successful, it returns a list of
    connections including the ProxyDB connection and the default
    Django database connection. If the ProxyDB connection fails,
    only the default Django connection is returned.

    Returns:
        List[sqlite3.Connection]: A list containing the SQLite
        connections. The list includes the ProxyDB connection (if
        successfully created) and the Django database connection.

    Note:
        The function assumes that `ProxyDB` and `connection` are
        accessible within the scope. `ProxyDB` should have a `db`
        attribute with a `conn` property that represents an SQLite
        connection. The `connection` refers to the default Django
        database connection.
    """
    db = None
    try:
        db = ProxyDB(get_relative_path("src/database.sqlite"), True)
    except Exception:
        pass
    return [db.db.conn, connection]


def execute_sql_query(
    sql: str, params: Optional[tuple] = None
) -> List[Union[List[tuple], None]]:
    """
    Executes an SQL query on all available database connections.

    This function uses the connections retrieved from `get_db_connections`
    to execute the provided SQL query. It returns the results from each
    connection. If a query execution fails on any connection, it logs the
    exception and continues with the next connection. After executing the
    query, all connections are closed.

    Args:
        sql (str): The SQL query to be executed. Use `?` as placeholders for
                   parameters when working with SQLite.
        params (Optional[tuple]): Optional parameters for the SQL query. This
                                  should be a tuple of values to be inserted
                                  into the placeholders in the SQL query.

    Returns:
        List[Union[List[tuple], None]]: A list where each element represents
        the result of the SQL query execution on a respective connection.
        `None` indicates that the query failed on that connection.

    Examples:
        # Example 1: Execute a SELECT query
        select_query = "SELECT * FROM proxies WHERE status = ?"
        results = execute_sql_query(select_query, ('active',))
        for result in results:
            if result is not None:
                print("Query results:", result)
            else:
                print("Query failed on one of the connections.")

        # Example 2: Execute a DELETE query
        proxy = '192.168.1.1:8080'
        delete_query = "DELETE FROM proxies WHERE proxy = ?"
        results = execute_sql_query(delete_query, (proxy,))
        for result in results:
            if result is not None:
                print("Query executed successfully.")
            else:
                print("Query failed on one of the connections.")

    Note:
        The function assumes that `ProxyDB` and `connection` are
        accessible within the scope. `ProxyDB` should have a `db`
        attribute with a `conn` property that represents an SQLite
        connection. The `connection` refers to the default Django
        database connection.
    """
    connections = get_db_connections()
    results = []

    for conn in connections:
        if conn:
            try:
                cursor = conn.cursor()
                cursor.execute(sql, params or ())
                results.append(cursor.fetchall())
                cursor.close()
            except Exception as e:
                # Log the exception for debugging purposes
                print(f"Error executing query on connection: {e}")
                results.append(None)
            finally:
                # Ensure the connection is closed
                conn.close()
        else:
            results.append(None)

    return results
