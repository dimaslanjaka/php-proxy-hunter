"""Simple MySQL connection test using src/MySQLHelper.py.

This script connects to the host 100.82.212.71:3306 with user `user` and
password `123456` using the `MySQLHelper` class from `src/MySQLHelper.py`.
No CLI argument parsing — it simply attempts the connection and prints the
result for quick verification.
"""

from __future__ import annotations

import os
import sys

# Ensure the `src` directory is importable so we can import MySQLHelper
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.insert(0, ROOT)

from src.func_platform import is_debug
from src.MySQLHelper import MySQLHelper
from src.shared import get_db_config
from tailscale.utils import (
    get_tailscale_ipv4,
    save_tailscale_status,
    upload_tailscale_status,
)


def run() -> int:
    save_tailscale_status()  # Update the saved Tailscale status JSON
    # Upload the Tailscale status JSON to the SFTP server
    if upload_tailscale_status():
        print("Tailscale status uploaded successfully")
    else:
        print("Failed to upload Tailscale status")
    host = get_tailscale_ipv4()  # Get the Tailscale IPv4 address to connect to
    print("Tailscale IPv4:", host)
    db_info = get_db_config()
    port = 3306
    user = db_info.get("mysql_user", "root")
    password = db_info.get("mysql_pass", "")
    if is_debug():
        print(f"Attempting MySQL connection to {host}:{port} with {user=}, {password=}")
    try:
        db = MySQLHelper(host=host, user=user, password=password, port=port)
        print("Connection successful")
        print("Server version:", db.mysql_version)
        db.close()
        return 0
    except Exception as exc:  # pragma: no cover - simple script
        print("Connection failed:", exc)
        return 1


if __name__ == "__main__":
    raise SystemExit(run())
