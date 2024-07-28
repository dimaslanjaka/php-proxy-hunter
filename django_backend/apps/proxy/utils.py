import os
import sys
from typing import List, Optional, Tuple, Union

from django.conf import settings

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

import sqlite3
from proxy_checker import *
from proxy_hunter import *

from src.func_proxy import *
from src.geoPlugin import *
from src.ProxyDB import ProxyDB
from django.db import connection


def get_db_connections() -> List[sqlite3.Connection]:
    """
    Retrieves database connections, ensuring that Django connections are only accessed
    when Django is fully initialized.
    """
    connections = []
    try:
        # Obtain Django SQLite connection
        database_path = settings.DATABASES["default"]["NAME"]
        db = ProxyDB(database_path, True)
        connections.append(db.db.conn if db else None)
    except Exception as e:
        print(f"Error accessing {database_path} connection: {e}")

    try:
        # Obtain PHP Proxy Hunter SQLite connection
        database_path = get_relative_path("src/database.sqlite")
        db = ProxyDB(database_path, True)
        connections.append(db.db.conn if db else None)
    except Exception as e:
        print(f"Error creating ProxyDB connection: {e}")

    return connections


def execute_sql_query(sql: str, params: Optional[Tuple] = None) -> dict:
    """
    Executes an SQL query on all available database connections.

    Args:
        sql (str): The SQL query to be executed. Use `?` as placeholders for
                   parameters when working with SQLite.
        params (Optional[tuple]): Optional parameters for the SQL query. This
                                  should be a tuple of values to be inserted
                                  into the placeholders in the SQL query.

    Returns:
        dict: A dictionary with two keys: 'error' and 'items'.
              'error' contains a list of error messages, and 'items' contains
              a list of results from successful query executions.
    """
    connections = get_db_connections()
    results = {"error": [], "items": []}

    for index, conn in enumerate(connections):
        conn_info = f"Connection {index}: {conn}" if conn else f"Connection {index}"

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
                    results["items"].append(query_results)
                else:
                    conn.commit()
                    results["items"].append(cursor.rowcount)

                cursor.close()
            except Exception as e:
                error_message = f"Error executing query {sql} on {conn_info}: {e}"
                print(error_message)  # Optional: Print the error message
                results["error"].append(error_message)
            finally:
                if hasattr(conn, "close"):
                    conn.close()
        else:
            results["error"].append(f"{conn_info} has no connection")

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
