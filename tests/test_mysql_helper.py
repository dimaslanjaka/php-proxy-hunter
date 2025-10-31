import os
import sys
import pytest


# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


from src.func import get_relative_path
from dotenv import load_dotenv

# Resolve .env located in the parent directory of the tests folder reliably
env_path = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".env"))
if os.path.isfile(env_path):
    load_dotenv(dotenv_path=env_path, override=True)

from src.MySQLHelper import MySQLHelper

MYSQL_HOST = os.getenv("MYSQL_HOST", "127.0.0.1")
MYSQL_USER = os.getenv("MYSQL_USER", "root")
MYSQL_PASS = os.getenv("MYSQL_PASS", "")
MYSQL_DB = "testdb"
try:
    MYSQL_PORT = int(os.getenv("MYSQL_PORT", "3306") or 3306)
except ValueError:
    MYSQL_PORT = 3306

# Debug: print resolved MySQL connection variables (password masked)
print(
    f"MYSQL_HOST={MYSQL_HOST}, "
    f"MYSQL_USER={MYSQL_USER}, "
    f"MYSQL_PASS={MYSQL_PASS}, "
    f"MYSQL_DB={MYSQL_DB}, "
    f"MYSQL_PORT={MYSQL_PORT}, "
    f"ENV_PATH={env_path if os.path.isfile(env_path) else '(not found)'}"
)


@pytest.mark.skipif(
    not (MYSQL_HOST and MYSQL_USER and MYSQL_DB),
    reason="MySQL credentials not set in environment",
)
def test_mysql_helper_crud(tmp_path):
    host = MYSQL_HOST
    if host == "localhost":
        host = "127.0.0.1"
    db = MySQLHelper(
        host=host,
        user=MYSQL_USER,
        password=MYSQL_PASS or "",
        database=MYSQL_DB,
        port=MYSQL_PORT,
    )
    table = "pytest_mysql_helper"
    # ensure table is clean
    try:
        db.execute_query(f"DROP TABLE IF EXISTS `{table}`")
    except Exception:
        pass

    # create table
    db.create_table(table, ["id INT AUTO_INCREMENT PRIMARY KEY", "name VARCHAR(255)"])

    # insert
    db.insert(table, {"name": "alice"})
    db.insert(table, {"name": "bob"})

    # select
    rows = db.select(table, "*")
    assert isinstance(rows, list)
    assert any(r["name"] == "alice" for r in rows)

    # count
    cnt = db.count(table)
    assert cnt >= 2

    # update
    db.update(table, {"name": "charlie"}, "name = %s", ("alice",))
    rows = db.select(table, where="name = %s", params=("charlie",))
    assert len(rows) == 1

    # delete
    db.delete(table, "name = %s", ("charlie",))
    cnt2 = db.count(table)
    assert cnt2 >= 1

    # cleanup
    db.execute_query(f"DROP TABLE IF EXISTS `{table}`")
    db.close()


if __name__ == "__main__":
    pytest.main([__file__])
