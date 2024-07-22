import sys, os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../")))

from src.func_proxy import *
from src.geoPlugin import *
from proxy_hunter import *
from proxy_checker import *
from src.ProxyDB import ProxyDB
from django.db import connection
import sqlite3


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
