import os
import sys

from proxy_hunter.extract_proxies import extract_proxies

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../../"))
SRC_DIR = os.path.join(BASE_DIR, "src")
sys.path.append(SRC_DIR)

from src.func import delete_path, get_relative_path, read_file

import sqlite3
from django.core.management.base import BaseCommand


class Command(BaseCommand):
    help = "Sync proxies table from src/database.sqlite to tmp/database.sqlite"

    def handle(self, *args, **kwargs):
        db1 = get_relative_path("src/database.sqlite")
        db2 = get_relative_path("tmp/database.sqlite")

        # sync active proxies first
        self.sync_active(db2, db1)
        self.sync_active(db1, db2)
        # sync all proxies
        self.sync(db1, db2)
        self.sync(db2, db1)
        # indexing proxies from uploaded files
        self.index(db1, db2)

    def sync(self, src, dest):
        """
        sync all proxies
        """
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
            insert_query = f"INSERT OR REPLACE INTO proxies ({', '.join(columns)}) VALUES ({placeholders})"

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
        """
        sync active proxies
        """
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

    def index(self, src, dest):
        """
        indexing proxies
        """
        files_content = read_all_text_files(get_relative_path("assets/proxies"))
        files_content[get_relative_path("proxies.txt")] = read_file(
            get_relative_path("proxies.txt")
        )

        for file_path, content in files_content.items():
            proxies = extract_proxies(content)
            print(f"Total proxies in {file_path}: {len(proxies)}")

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

                # Connect to the database
                conn = sqlite3.connect(db_path)

                try:
                    cursor = conn.cursor()

                    # Execute the insert or replace query
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
                            f"Error occurred while inserting/replacing proxies: {e}"
                        )
                    )

                finally:
                    conn.close()

            # Execute the function for both src and dest databases
            execute_insert_or_replace(src, proxy_data)
            execute_insert_or_replace(dest, proxy_data)
            delete_path(file_path)


def read_all_text_files(directory):
    text_files_content = {}

    # List all files in the directory
    for filename in os.listdir(directory):
        # Check if the file is a text file
        if filename.endswith(".txt"):
            file_path = os.path.join(directory, filename)
            try:
                with open(file_path, "r", encoding="utf-8") as file:
                    text_files_content[file_path] = file.read()
            except Exception as e:
                print(f"Error reading {file_path}: {e}")

    return text_files_content
