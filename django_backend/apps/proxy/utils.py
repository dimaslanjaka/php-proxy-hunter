import os
import sys
from typing import List, Optional, Tuple, Union

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

import sqlite3

from django.db import connection
from proxy_checker import *
from proxy_hunter import *

from src.func_proxy import *
from src.geoPlugin import *
from src.ProxyDB import ProxyDB


def get_db_connections() -> List[Union[sqlite3.Connection]]:
    """
    Attempts to retrieve database connections.
    """
    db = None
    try:
        db = ProxyDB(get_relative_path("src/database.sqlite"), True)
    except Exception as e:
        print(f"Error creating ProxyDB connection: {e}")

    connections = [db.db.conn if db else None, connection]
    active_connections = [conn for conn in connections if conn]
    return active_connections


def execute_sql_query(
    sql: str, params: Optional[Tuple] = None
) -> List[Union[List[tuple], None]]:
    """
    Executes an SQL query on all available database connections.

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
    """
    connections = get_db_connections()
    results = []

    for conn in connections:
        if conn:
            try:
                cursor = conn.cursor()
                # Detect if the connection is from Django
                if isinstance(conn, connection.__class__):
                    # For Django connection, use %s placeholders
                    sql_django = sql.replace("?", "%s")
                    cursor.execute(sql_django, params or ())
                else:
                    # For SQLite connection, use ? placeholders
                    cursor.execute(sql, params or ())

                if sql.strip().upper().startswith("SELECT"):
                    query_results = cursor.fetchall()
                    results.append(query_results)
                else:
                    conn.commit()
                    results.append(cursor.rowcount)

                cursor.close()
            except Exception as e:
                print(f"Error executing query {sql} on connection {conn}: {e}")
                results.append(None)
            finally:
                if isinstance(conn, sqlite3.Connection):
                    conn.close()
        else:
            results.append(None)

    return results


def execute_select_query(
    sql: str, params: Optional[Tuple] = None
) -> List[Dict[str, Union[str, int, float, None]]]:
    """
    Executes a SELECT SQL query on all available database connections and
    returns the results as a list of dictionaries.

    Args:
        sql (str): The SQL SELECT query to be executed. Use `?` as placeholders for
                   parameters when working with SQLite.
        params (Optional[Tuple]): Optional parameters for the SQL query. This
                                  should be a tuple of values to be inserted
                                  into the placeholders in the SQL query.

    Returns:
        List[Dict[str, Union[str, int, float, None]]]: A list of dictionaries where
        each dictionary represents a row in the result set. The keys are the
        column names, and the values are the corresponding data in the row.
    """
    connections = get_db_connections()
    results = []

    for conn in connections:
        if conn:
            try:
                cursor = conn.cursor()
                # Detect if the connection is from Django
                if isinstance(conn, connection.__class__):
                    # For Django connection, use %s placeholders
                    sql_django = sql.replace("?", "%s")
                    cursor.execute(sql_django, params or ())
                else:
                    # For SQLite connection, use ? placeholders
                    cursor.execute(sql, params or ())

                # Fetch the results and convert them into a list of dictionaries
                columns = [desc[0] for desc in cursor.description]
                rows = cursor.fetchall()
                for row in rows:
                    row_dict = dict(zip(columns, row))
                    results.append(row_dict)

                cursor.close()
            except Exception as e:
                print(f"Error executing query {sql} on connection {conn}: {e}")
            finally:
                if isinstance(conn, sqlite3.Connection):
                    conn.close()
        else:
            print(f"Connection {conn} is None")

    return results
