import os
import sys

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

import sqlite3

from django.core.management.base import BaseCommand
from proxy_hunter import delete_path, extract_proxies, read_all_text_files, read_file

from src.func import get_relative_path
from src.geoPlugin import download_databases


class Command(BaseCommand):
    help = "Sync proxies table from src/database.sqlite to tmp/database.sqlite"

    def handle(self, *args, **kwargs):
        download_databases(get_relative_path("src"))

        db1 = get_relative_path("src/database.sqlite")
        db2 = get_relative_path("tmp/database.sqlite")

        # Sync active proxies first
        self.sync_active(db1, db2)
        self.sync_active(db2, db1)

        # Sync all proxies
        self.sync(db1, db2)
        self.sync(db2, db1)

        # Indexing proxies from uploaded files
        self.index(db1, db2)

    def sync(self, src, dest):
        """
        Sync all proxies from src to dest
        """
        self.stdout.write(
            self.style.SUCCESS(f"Starting to sync all proxies from {src} to {dest}...")
        )

        src_conn = sqlite3.connect(src)
        dest_conn = sqlite3.connect(dest)

        try:
            src_cursor = src_conn.cursor()
            dest_cursor = dest_conn.cursor()

            # Fetch column names excluding 'id' from the source database
            src_cursor.execute("PRAGMA table_info(proxies)")
            columns_info = src_cursor.fetchall()
            columns = [column[1] for column in columns_info if column[1] != "id"]

            # Fetch all rows from the source database
            src_cursor.execute("SELECT * FROM proxies")
            src_proxies = src_cursor.fetchall()

            # Fetch all rows from the destination database
            dest_cursor.execute("SELECT * FROM proxies")
            dest_proxies = {tuple(row[1:]) for row in dest_cursor.fetchall()}

            # Prepare insert query for new rows
            placeholders = ", ".join(["?" for _ in columns])
            insert_query = f"INSERT OR IGNORE INTO proxies ({', '.join(columns)}) VALUES ({placeholders})"

            # Insert rows into destination database if they don't exist
            new_rows_count = 0
            for row in src_proxies:
                row_data = [
                    row[i] for i, col in enumerate(columns_info) if col[1] != "id"
                ]
                if tuple(row_data) not in dest_proxies:
                    dest_cursor.execute(insert_query, row_data)
                    new_rows_count += 1

            dest_conn.commit()
            self.stdout.write(
                self.style.SUCCESS(
                    f"Successfully synced {new_rows_count} new proxies from {src} to {dest}."
                )
            )

        except Exception as e:
            self.stderr.write(
                self.style.ERROR(
                    f"Error occurred while syncing from {src} to {dest}: {e}"
                )
            )

        finally:
            src_conn.close()
            dest_conn.close()

    def sync_active(self, src, dest):
        """
        Sync active proxies from src to dest
        """
        self.stdout.write(
            self.style.SUCCESS(
                f"Starting to sync active proxies from {src} to {dest}..."
            )
        )

        src_conn = sqlite3.connect(src)
        dest_conn = sqlite3.connect(dest)

        try:
            src_cursor = src_conn.cursor()
            dest_cursor = dest_conn.cursor()

            # Fetch column names excluding 'id' from the source database
            src_cursor.execute("PRAGMA table_info(proxies)")
            columns_info = src_cursor.fetchall()
            columns = [column[1] for column in columns_info if column[1] != "id"]

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
                dest_cursor.execute(insert_query, row_data)
                new_rows_count += 1

            dest_conn.commit()
            self.stdout.write(
                self.style.SUCCESS(
                    f"Successfully synced {new_rows_count} active proxies from {src} to {dest}."
                )
            )

        except Exception as e:
            self.stderr.write(
                self.style.ERROR(
                    f"Error occurred while syncing active proxies from {src} to {dest}: {e}"
                )
            )

        finally:
            src_conn.close()
            dest_conn.close()

    def index(self, src, dest):
        """
        Index proxies from uploaded files and insert them into src and dest databases
        """
        files_content = read_all_text_files(get_relative_path("assets/proxies"))
        if os.path.exists(get_relative_path("proxies.txt")):
            files_content[get_relative_path("proxies.txt")] = read_file(
                get_relative_path("proxies.txt")
            )

        for file_path, content in files_content.items():
            proxies = extract_proxies(content)
            self.stdout.write(
                f"Total proxies extracted from {file_path}: {len(proxies)}"
            )

            # Prepare the list of proxies to insert/replace, handling None values
            proxy_data = [
                (
                    item.proxy,
                    item.username if item.username is not None else "",
                    item.password if item.password is not None else "",
                )
                for item in proxies
            ]

            # Define the insert or replace query
            insert_query = "INSERT OR IGNORE INTO proxies (proxy, username, password) VALUES (?, ?, ?)"

            # Function to execute the insert or replace query
            def execute_insert_or_replace(db_path, proxy_data):
                self.stdout.write(
                    self.style.SUCCESS(
                        f"Starting to insert or replace proxies in {db_path}..."
                    )
                )

                conn = sqlite3.connect(db_path)

                try:
                    cursor = conn.cursor()
                    cursor.executemany(insert_query, proxy_data)
                    conn.commit()
                    self.stdout.write(
                        self.style.SUCCESS(
                            f"Successfully inserted or replaced {len(proxy_data)} proxies in {db_path}."
                        )
                    )
                except Exception as e:
                    self.stderr.write(
                        self.style.ERROR(
                            f"Error occurred while inserting/replacing proxies in {db_path}: {e}"
                        )
                    )
                finally:
                    conn.close()

            # Execute the function for both src and dest databases
            execute_insert_or_replace(src, proxy_data)
            execute_insert_or_replace(dest, proxy_data)
            delete_path(file_path)
