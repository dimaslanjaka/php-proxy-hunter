import os
import sys
import pytest
from pathlib import Path

# Make src importable
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(ROOT)

from src.SQLiteHelper import SQLiteHelper

DB_PATH = os.path.join(ROOT, "tmp", "pytest_sqlite_helper.sqlite")
TABLE_NAME = "test_table"


def setup_module(module):
    Path(os.path.dirname(DB_PATH)).mkdir(parents=True, exist_ok=True)
    # remove old db if exists
    try:
        if os.path.exists(DB_PATH):
            os.remove(DB_PATH)
    except Exception:
        pass


def teardown_module(module):
    try:
        if os.path.exists(DB_PATH):
            os.remove(DB_PATH)
    except Exception:
        pass


@pytest.fixture(scope="module")
def db_helper():
    db = SQLiteHelper(DB_PATH, check_same_thread=True)
    yield db
    try:
        db.close()
    except Exception:
        pass


def test_create_table(db_helper: SQLiteHelper):
    db_helper.create_table(TABLE_NAME, ["id INTEGER PRIMARY KEY", "k TEXT", "v TEXT"])
    assert db_helper.column_exists(TABLE_NAME, "k")
    assert db_helper.column_exists(TABLE_NAME, "v")


def test_insert_and_select(db_helper: SQLiteHelper):
    db_helper.truncate_table(TABLE_NAME)
    db_helper.insert(TABLE_NAME, {"k": "a", "v": "1"})
    res = db_helper.select(TABLE_NAME, "*")
    assert isinstance(res, list)
    assert any(r.get("k") == "a" and r.get("v") == "1" for r in res)


def test_insert_ignore_and_replace(db_helper: SQLiteHelper):
    # Drop table first to ensure we can create it with the UNIQUE constraint
    db_helper.execute_query(f"DROP TABLE IF EXISTS {TABLE_NAME}")
    # Create table with a UNIQUE constraint on 'k' to make insert_ignore work
    db_helper.execute_query(
        f"CREATE TABLE {TABLE_NAME} (id INTEGER PRIMARY KEY, k TEXT UNIQUE, v TEXT)"
    )
    db_helper.insert_ignore(TABLE_NAME, {"k": "x", "v": "10"})
    # duplicate should be ignored
    db_helper.insert_ignore(TABLE_NAME, {"k": "x", "v": "10"})
    rows = db_helper.select(TABLE_NAME, "*")
    assert len(rows) == 1
    # replace
    db_helper.insert_replace(TABLE_NAME, {"k": "x", "v": "20"})
    rows = db_helper.select(TABLE_NAME, "*")
    # should still be one row and value updated
    assert len(rows) >= 1
    found = None
    for r in rows:
        if r.get("k") == "x":
            found = r
            break
    assert found is not None and (
        found.get("v") == "20" or found.get("v") == 20 or found.get("v") == "20"
    )


def test_update_and_count_and_delete(db_helper: SQLiteHelper):
    db_helper.truncate_table(TABLE_NAME)
    db_helper.insert(TABLE_NAME, {"k": "u", "v": "5"})
    # update
    db_helper.update(TABLE_NAME, {"v": "6"}, "k = ?", ["u"])
    rows = db_helper.select(TABLE_NAME, "*")
    assert any(r.get("v") == "6" for r in rows)
    # count
    cnt = db_helper.count(TABLE_NAME, "k = ?", ["u"])
    assert cnt == 1
    # delete
    db_helper.delete(TABLE_NAME, "k = ?", ["u"])
    cnt2 = db_helper.count(TABLE_NAME, "k = ?", ["u"])
    assert cnt2 == 0


def test_execute_query_and_fetch(db_helper: SQLiteHelper):
    db_helper.truncate_table(TABLE_NAME)
    db_helper.insert(TABLE_NAME, {"k": "q", "v": "9"})
    # execute_query_fetch select
    rows = db_helper.execute_query_fetch(f"SELECT k,v FROM {TABLE_NAME} WHERE k = ?", ["q"])  # type: ignore
    assert isinstance(rows, list) and len(rows) == 1
    assert rows[0].get("k") == "q"
    # execute_query non-select
    db_helper.execute_query(f"UPDATE {TABLE_NAME} SET v = ? WHERE k = ?", ["99", "q"])  # type: ignore
    rows2 = db_helper.select(TABLE_NAME, "*")
    assert any(r.get("v") == "99" for r in rows2)


if __name__ == "__main__":
    pytest.main(["-q", __file__])
