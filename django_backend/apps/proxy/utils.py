import os
import sys
from typing import List, Optional, Tuple, Union

from django.conf import settings

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

import sqlite3

from django.db import connection
from proxy_checker import *
from proxy_hunter import *

from src.func_proxy import *
from src.geoPlugin import *
from src.ProxyDB import ProxyDB
from src.SQLiteHelper import MyDatabaseConnection


def print_db_error(conn: MyDatabaseConnection, sql: str, error: Exception):
    db_path = None
    try:
        for id_, name, filename in conn.execute("PRAGMA database_list"):
            if name == "main" and filename is not None:
                db_path = filename
                break
    except Exception:
        pass
    msg = f"Error executing query {sql} on connection {db_path}: {error}"
    print(msg)
    return msg


def get_connection(database_path: str):
    """
    Helper function to retrieve a database connection.
    """
    try:
        db = ProxyDB(database_path, True)
        return db.db.conn if db.db is not None else None
    except Exception as e:
        print(f"Error accessing database at {database_path}: {e}")
        return None


def get_db_connections() -> List[MyDatabaseConnection]:
    """
    Retrieves a list of active database connections.
    """
    connections = []
    # Django database connection
    django_db_path = settings.DATABASES["default"]["NAME"]
    django_conn = get_connection(django_db_path)
    if django_conn:
        connections.append(django_conn)

    # PHP Proxy Hunter database connection
    php_proxy_db_path = get_relative_path("src/database.sqlite")
    php_proxy_conn = get_connection(php_proxy_db_path)
    if php_proxy_conn:
        connections.append(php_proxy_conn)

    return connections


def format_query(query: str, params: Optional[Tuple] = None) -> str:
    """
    Formats an SQL query by substituting placeholders with actual parameter values.

    Args:
        query (str): The SQL query string containing placeholders (e.g., '?').
        params (Optional[Tuple]): A tuple of parameters to substitute into the query.
                                  If None, the original query is returned unchanged.

    Returns:
        str: The SQL query with placeholders replaced by actual parameter values.

    Example:
        >>> query = "SELECT * FROM my_table WHERE column1 = ? AND column2 = ?"
        >>> params = ('value1', 'value2')
        >>> format_query(query, params)
        "SELECT * FROM my_table WHERE column1 = 'value1' AND column2 = 'value2'"
    """
    if params is None:
        return query

    # Create a list of parameters, ensuring correct formatting
    formatted_params = []
    for param in params:
        if isinstance(param, str):
            formatted_params.append(f"'{param}'")
        elif param is None:
            formatted_params.append("NULL")
        else:
            formatted_params.append(str(param))

    # Replace ? placeholders with actual parameters
    formatted_query = re.sub(
        r"\?", lambda _: formatted_params.pop(0), query, count=len(params)
    )

    return formatted_query


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
                print_db_error(conn=conn, sql=sql, error=e)
            finally:
                if isinstance(conn, sqlite3.Connection):
                    conn.close()
        else:
            print(f"Connection {conn} is None")

    return results


def adjust_sql_insert_query(sql: str, params: Tuple) -> Tuple[str, Tuple]:
    """
    Adjusts the SQL query and parameters based on None or 'NULL' values.

    Args:
        sql (str): The original SQL query with placeholders.
        params (Tuple): The parameters for the SQL query.

    Returns:
        Tuple[str, Tuple]: The adjusted SQL query and parameters.
    """
    # Ensure the SQL statement contains 'INSERT'
    if "INSERT" not in sql:
        raise ValueError("SQL query must be an INSERT statement")

    # Extract columns and values parts from the SQL query
    try:
        columns_part = sql.split("VALUES")[0].strip()
        values_part = sql.split("VALUES")[1].strip()
    except IndexError:
        raise ValueError("SQL query must contain 'VALUES' clause")

    # Extract column names
    columns = columns_part.split("(")[1].split(")")[0].split(",")
    columns = [col.strip() for col in columns]

    # Filter parameters and adjust columns
    filtered_params = []
    filtered_columns = []
    placeholders = []

    for column, param in zip(columns, params):
        if param is not None and param != "NULL":
            filtered_columns.append(column)
            filtered_params.append(param)
            placeholders.append("?")

    # Check if any columns or parameters remain after filtering
    if not filtered_columns:
        raise ValueError("No valid columns and parameters to insert")

    # Rebuild SQL query
    new_columns_part = f"({', '.join(filtered_columns)})"
    new_values_part = f"VALUES ({', '.join(placeholders)})"
    # Correct the query to exclude extra columns in the part before VALUES
    new_sql = f"{columns_part.split('VALUES')[0].split('(')[0].strip()} {new_columns_part} {new_values_part}"

    return new_sql, tuple(filtered_params)


def execute_sql_query(
    sql: str, params: Optional[Tuple] = None, debug: Optional[bool] = False
) -> dict:
    """
    Executes an SQL query on all available database connections.

    Args:
        sql (str): The SQL query to be executed. Use `?` as placeholders for
                   parameters when working with SQLite.
        params (Optional[tuple]): Optional parameters for the SQL query. This
                                  should be a tuple of values to be inserted
                                  into the placeholders in the SQL query.
        debug (Optional[bool]): If True, prints debugging information.

    Returns:
        dict: A dictionary with two keys: 'error' and 'items'.
              'error' contains a list of error messages, and 'items' contains
              a list of results from successful query executions.
    """
    connections = get_db_connections()
    results = {"error": [], "items": [], "query": format_query(sql, params)}

    if params is None:
        params = ()

    try:
        # Adjust SQL query and parameters
        if sql.lower().strip().startswith("insert"):
            sql, params = adjust_sql_insert_query(sql, params)
    except ValueError as e:
        results["error"].append(str(e))
        return results

    for index, conn in enumerate(connections):
        conn_info = f"Connection {index}: {conn}" if conn else f"Connection {index}"

        if debug:
            print(f"[SQLite] Processing {conn_info}")

        if conn:
            try:
                cursor = conn.cursor()
                if debug:
                    print(f"[SQLite] Executing SQL: {sql} with params: {params}")

                # Detect if the connection is from Django
                if isinstance(conn, connection.__class__):
                    # For Django connection, use %s placeholders
                    sql_django = sql.replace("?", "%s")
                    cursor.execute(sql_django, params)
                else:
                    # For SQLite connection, use ? placeholders
                    cursor.execute(sql, params)

                if sql.strip().upper().startswith("SELECT"):
                    query_results = cursor.fetchall()
                    results["items"].append(query_results)
                    if debug:
                        print(f"[SQLite] Fetched results: {query_results}")
                else:
                    conn.commit()
                    results["items"].append(cursor.rowcount)
                    if debug:
                        print(f"[SQLite] Affected rows: {cursor.rowcount}")

                cursor.close()
            except Exception as e:
                error_message = print_db_error(conn=conn, sql=sql, error=e)
                results["error"].append(error_message)
            finally:
                if hasattr(conn, "close"):
                    conn.close()
                    if debug:
                        print(f"[SQLite] Closed {conn_info}")
        else:
            results["error"].append(f"{conn_info} has no connection")

    return results
