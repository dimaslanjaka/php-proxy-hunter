import os
import sys
import pytest
from dotenv import find_dotenv, load_dotenv

# Make src importable
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(ROOT)

from src.ProxyDB import ProxyDB


# ---------------------------------------
#  Environment loading (sessionwide)
# ---------------------------------------


@pytest.fixture(scope="session", autouse=True)
def load_env():
    """Load .env exactly once per session."""
    env_file = find_dotenv(filename=".env", usecwd=True)
    print(f"[pytest] Loading env file: {env_file}")
    load_dotenv(env_file)


# ---------------------------------------
#  MySQL config (lazy-loaded)
# ---------------------------------------


@pytest.fixture(scope="session")
def mysql_settings():
    """Load MySQL settings from environment."""
    return {
        "dbname": "phpunit_test_db",
        "host": os.getenv("MYSQL_HOST"),
        "user": os.getenv("MYSQL_USER"),
        "password": os.getenv("MYSQL_PASS") or "",
    }


# ---------------------------------------
#  Main DB fixture
# ---------------------------------------


@pytest.fixture(scope="module")
def mysql_proxy_db(mysql_settings):
    """Provide initialized ProxyDB instance or skip tests."""
    host = mysql_settings["host"]
    user = mysql_settings["user"]

    if not host or not user:
        pytest.skip("Skipping MySQL tests: MYSQL_HOST or MYSQL_USER not set")

    print(f"[pytest] Using MySQL host={host} user={user} db={mysql_settings['dbname']}")

    try:
        db = ProxyDB(
            None,
            db_type="mysql",
            mysql_host=host,
            mysql_dbname=mysql_settings["dbname"],
            mysql_user=user,
            mysql_password=mysql_settings["password"],
            start=True,
        )
    except Exception as e:
        pytest.skip(f"Skipping MySQL tests: cannot connect ({e})")

    yield db

    # Best effort cleanup
    close_fn = getattr(db, "close", None) or getattr(
        getattr(db, "db", None), "close", None
    )
    if callable(close_fn):
        try:
            close_fn()
        except Exception:
            pass


# ---------------------------------------
#  Shared test data
# ---------------------------------------

TEST_PROXY = "176.126.103.194:44214"


# ---------------------------------------
#  Tests
# ---------------------------------------


@pytest.fixture(autouse=True)
def ensure_clean_state(mysql_proxy_db):
    """Automatically remove the test proxy before/after each test."""
    db = mysql_proxy_db
    try:
        db.remove(TEST_PROXY)
    except Exception:
        pass
    yield
    try:
        db.remove(TEST_PROXY)
    except Exception:
        pass


def test_add_and_select_proxy(mysql_proxy_db):
    db = mysql_proxy_db
    db.add(TEST_PROXY)
    res = db.select(TEST_PROXY)
    assert res, "select should return at least one row"
    assert res[0]["proxy"] == TEST_PROXY


def test_update_proxy(mysql_proxy_db):
    db = mysql_proxy_db
    db.add(TEST_PROXY)
    db.update(
        TEST_PROXY,
        "http",
        "SomeRegion",
        "SomeCity",
        "SomeCountry",
        "active",
        "123ms",
        "UTC+7",
    )
    res = db.select(TEST_PROXY)
    first = res[0]
    assert first.get("type") == "http"
    assert first.get("city") == "SomeCity"
    assert first.get("status") == "active"


def test_remove_proxy(mysql_proxy_db):
    db = mysql_proxy_db
    db.add(TEST_PROXY)
    db.remove(TEST_PROXY)
    assert not db.select(TEST_PROXY)


def test_is_already_added_and_mark_as_added(mysql_proxy_db):
    db = mysql_proxy_db
    assert not db.is_already_added(TEST_PROXY)
    db.mark_as_added(TEST_PROXY)
    assert db.is_already_added(TEST_PROXY)


def test_get_all_proxies(mysql_proxy_db):
    db = mysql_proxy_db
    db.add(TEST_PROXY)
    assert db.get_all_proxies()


# ---------------------------------------
#  Run programmatically
# ---------------------------------------

if __name__ == "__main__":
    pytest.main(["-vvv", "-s", __file__])
