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
    """
    db = None
    try:
        db = ProxyDB(get_relative_path("src/database.sqlite"), True)
        # print("ProxyDB connection created.")
    except Exception as e:
        print(f"Error creating ProxyDB connection: {e}")

    connections = [db.db.conn if db else None, connection]
    # Filter out None values
    active_connections = [conn for conn in connections if conn]

    # print(f"Active connections: {len(active_connections)}")

    return active_connections


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
                # print(f"Executing query on connection: {conn}")
                cursor.execute(sql, params or ())
                query_results = cursor.fetchall()
                # print(f"Query results: {query_results}")
                results.append(query_results)
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

    # Debug
    # print(f"Total active connections {len(connections)}")
    # print(f"Results from all connections: {results}")

    return results
