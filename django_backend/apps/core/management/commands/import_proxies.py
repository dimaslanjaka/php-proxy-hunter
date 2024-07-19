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
        self.sync(db2, db1)

    def sync(self, src, dest):
        self.stdout.write(self.style.SUCCESS(f"Starting to sync proxies to {dest}..."))

        # Connect to source and destination databases
        src_conn = sqlite3.connect(src)
        tmp_conn = sqlite3.connect(dest)

        try:
            src_cursor = src_conn.cursor()
            tmp_cursor = tmp_conn.cursor()

            # Fetch all proxies from source database
            src_cursor.execute("SELECT proxy FROM proxies")
            src_proxies = src_cursor.fetchall()

            # Fetch all proxy values from destination database
            tmp_cursor.execute("SELECT proxy FROM proxies")
            tmp_proxies = {row[0] for row in tmp_cursor.fetchall()}

            # Prepare insert query for new proxies
            insert_query = "INSERT INTO proxies (proxy) VALUES (?)"

            # Insert proxies into destination database if they don't exist
            new_proxies_count = 0
            for proxy in src_proxies:
                if proxy[0] not in tmp_proxies:
                    tmp_cursor.execute(insert_query, (proxy[0],))
                    new_proxies_count += 1

            tmp_conn.commit()
            self.stdout.write(
                self.style.SUCCESS(
                    f"Successfully synced {new_proxies_count} new proxies."
                )
            )

        except Exception as e:
            self.stderr.write(self.style.ERROR(f"Error occurred: {e}"))

        finally:
            src_conn.close()
            tmp_conn.close()
