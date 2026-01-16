import os
import sys
from typing import Union

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

from src.MySQLHelper import MySQLHelper
from src.SQLiteHelper import SQLiteHelper

MIGRATION_NUMBER = 1


def migrate(db: Union[SQLiteHelper, MySQLHelper]):
    # Add column 'classification' to 'proxies' table when not exists
    if not db.column_exists("proxies", "classification"):
        if isinstance(db, SQLiteHelper):
            db.execute_query(
                "ALTER TABLE proxies ADD COLUMN classification TEXT DEFAULT ''"
            )
        elif isinstance(db, MySQLHelper):
            db.execute_query(
                "ALTER TABLE proxies ADD COLUMN classification VARCHAR(255) DEFAULT ''"
            )
