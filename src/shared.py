#!/usr/bin/env python3
"""
Shared database initialization and configuration.

Similar to php_backend/shared.php - handles .env loading and database setup.
"""

import os
import sys

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

# Load environment variables from .env file
try:
    from dotenv import load_dotenv

    env_file = os.path.join(os.path.dirname(__file__), "..", ".env")
    if os.path.exists(env_file):
        load_dotenv(env_file)
except ImportError:
    pass
except Exception as e:
    print(f"Error loading .env: {e}")

from src.ProxyDB import ProxyDB
from src.func import get_relative_path


def init_db(db_type: str = "sqlite", production: bool = False):
    """
    Initialize ProxyDB with environment configuration.

    Args:
        db_type (str): Database type - 'sqlite' or 'mysql' (default: sqlite)
        production (bool): When True, force use of production MySQL configuration.

    Environment variables:
    - MYSQL_HOST: MySQL host (default: localhost)
    - MYSQL_DBNAME: MySQL database name (default: php_proxy_hunter)
    - MYSQL_USER: MySQL user (default: root)
    - MYSQL_PASS: MySQL password (default: empty)
    - MYSQL_HOST_PRODUCTION: Production host
    - MYSQL_USER_PRODUCTION: Production user
    - MYSQL_PASS_PRODUCTION: Production password

    Returns:
        ProxyDB: Initialized database instance
    """
    if production:
        db_type = "mysql"

    db_type = db_type.lower()

    if db_type == "mysql":
        # MySQL configuration
        if production:
            db_name = os.getenv("MYSQL_DBNAME", "php_proxy_hunter")
        else:
            db_name = os.getenv("MYSQL_DBNAME", "php_proxy_hunter_test")
        if production:
            db_host = os.getenv(
                "MYSQL_HOST_PRODUCTION", os.getenv("MYSQL_HOST", "localhost")
            )
            db_user = os.getenv(
                "MYSQL_USER_PRODUCTION", os.getenv("MYSQL_USER", "root")
            )
            db_pass = os.getenv("MYSQL_PASS_PRODUCTION", os.getenv("MYSQL_PASS", ""))
        else:
            db_host = os.getenv("MYSQL_HOST", "localhost")
            db_user = os.getenv("MYSQL_USER", "root")
            db_pass = os.getenv("MYSQL_PASS", "")

        proxy_db = ProxyDB(
            db_location=db_name,
            start=True,
            db_type="mysql",
            mysql_host=db_host,
            mysql_dbname=db_name,
            mysql_user=db_user,
            mysql_password=db_pass,
        )
    else:
        # SQLite configuration (default)
        db_file = get_relative_path("src/database.sqlite")

        proxy_db = ProxyDB(
            db_location=db_file,
            start=True,
            db_type="sqlite",
        )

    return proxy_db


def init_readonly_db():
    """
    Initialize a read-only ProxyDB instance.

    Returns:
        ProxyDB: Read-only database instance
    """
    proxy_db = ProxyDB(
        start=True,
        db_type="mysql",
        mysql_dbname="myproject",
        mysql_host="23.94.85.180",
        mysql_user="proxyuser",
        mysql_password="proxypassword",
    )

    return proxy_db


if __name__ == "__main__":
    # Specify database type manually here
    # proxy_db = init_db(db_type="mysql")
    # working = proxy_db.get_working_proxies(False, 10)
    # print(working)

    readonly_db = init_readonly_db()
    working = readonly_db.get_working_proxies(False, 10)
    print(working)
