import os
import sys

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

from src.func import get_relative_path

import sqlite3
from django.core.management.base import BaseCommand


class Command(BaseCommand):
    help = "Sync proxies table from src/database.sqlite to tmp/database.sqlite"

    def handle(self, *args, **kwargs):
        db1 = get_relative_path("src/database.sqlite")
        db2 = get_relative_path("tmp/database.sqlite")

        self.sync(db1, db2)
        self.sync_active(db1, db2)
        self.sync(db2, db1)
        self.sync_active(db2, db1)

    def sync(self, src, dest):
        self.stdout.write(self.style.SUCCESS(f"Starting to sync proxies to {dest}..."))

        # Connect to source and destination databases
        src_conn = sqlite3.connect(src)
        tmp_conn = sqlite3.connect(dest)

        try:
            src_cursor = src_conn.cursor()
            tmp_cursor = tmp_conn.cursor()

            # Fetch column names excluding 'id' from the source database
            src_cursor.execute("PRAGMA table_info(proxies)")
            columns_info = src_cursor.fetchall()
            columns = [
                column[1] for column in columns_info if column[1] != "id"
            ]  # Exclude 'id'

            # Fetch all rows from the source database
            src_cursor.execute("SELECT * FROM proxies")
            src_proxies = src_cursor.fetchall()

            # Fetch all rows from the destination database
            tmp_cursor.execute("SELECT * FROM proxies")
            tmp_proxies = {
                tuple(row[1:]) for row in tmp_cursor.fetchall()
            }  # Skip 'id' column

            # Prepare insert query for new rows
            placeholders = ", ".join(["?" for _ in columns])
            insert_query = f"INSERT OR IGNORE INTO proxies ({', '.join(columns)}) VALUES ({placeholders})"

            # Insert rows into destination database if they don't exist
            new_rows_count = 0
            for row in src_proxies:
                row_data = [
                    row[i] for i, col in enumerate(columns_info) if col[1] != "id"
                ]
                if tuple(row_data) not in tmp_proxies:
                    tmp_cursor.execute(insert_query, row_data)
                    new_rows_count += 1

            tmp_conn.commit()
            self.stdout.write(
                self.style.SUCCESS(f"Successfully synced {new_rows_count} new proxies.")
            )

        except Exception as e:
            self.stderr.write(self.style.ERROR(f"Error occurred: {e}"))

        finally:
            src_conn.close()
            tmp_conn.close()

    def sync_active(self, src, dest):
        self.stdout.write(
            self.style.SUCCESS(f"Starting to sync active proxies to {dest}...")
        )

        # Connect to source and destination databases
        src_conn = sqlite3.connect(src)
        tmp_conn = sqlite3.connect(dest)

        try:
            src_cursor = src_conn.cursor()
            tmp_cursor = tmp_conn.cursor()

            # Fetch column names excluding 'id' from the source database
            src_cursor.execute("PRAGMA table_info(proxies)")
            columns_info = src_cursor.fetchall()
            columns = [
                column[1] for column in columns_info if column[1] != "id"
            ]  # Exclude 'id'

            # Fetch active proxies from the source database
            src_cursor.execute("SELECT * FROM proxies WHERE status = 'active'")
            src_proxies = src_cursor.fetchall()

            # Prepare insert query for replacing rows
            placeholders = ", ".join(["?" for _ in columns])
            insert_query = f"INSERT OR REPLACE INTO proxies ({', '.join(columns)}) VALUES ({placeholders})"

            # Replace rows in destination database
            new_rows_count = 0
            for row in src_proxies:
                row_data = [
                    row[i] for i, col in enumerate(columns_info) if col[1] != "id"
                ]
                tmp_cursor.execute(insert_query, row_data)
                new_rows_count += 1

            tmp_conn.commit()
            self.stdout.write(
                self.style.SUCCESS(
                    f"Successfully synced {new_rows_count} active proxies (replaced)."
                )
            )

        except Exception as e:
            self.stderr.write(self.style.ERROR(f"Error occurred: {e}"))

        finally:
            src_conn.close()
            tmp_conn.close()
