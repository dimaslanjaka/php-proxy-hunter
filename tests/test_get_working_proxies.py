import os
import sys
import pytest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.ProxyDB import ProxyDB
from src.MySQLHelper import MySQLHelper
from dotenv import find_dotenv, load_dotenv


@pytest.fixture(scope="session", autouse=True)
def load_env() -> None:
    env_file = find_dotenv(filename=".env", usecwd=True)
    print(f"Loading env file: {env_file}")
    load_dotenv(env_file)


@pytest.fixture(scope="module", params=["sqlite", "mysql"])
def proxy_db(request):
    db_type = request.param
    db_host = os.getenv("MYSQL_HOST", "localhost")
    db_user = os.getenv("MYSQL_USER", "root")
    db_pass = os.getenv("MYSQL_PASS", "")
    db = ProxyDB(
        db_type=db_type,
        start=True,
        db_location="tmp/database.sqlite",
        mysql_dbname="php_proxy_hunter_test",
        mysql_host=db_host,
        mysql_user=db_user,
        mysql_password=db_pass,
    )

    # If MySQL requested but not available/connected, skip tests for it.
    if db_type == "mysql":
        try:
            if not db.db or not isinstance(db.db, MySQLHelper):
                pytest.skip("MySQL backend not available for tests")
        except Exception:
            pytest.skip("MySQL backend not available for tests")

    yield db

    try:
        close_fn = getattr(db, "close", None) or getattr(
            getattr(db, "db", None), "close", None
        )
        if callable(close_fn):
            close_fn()
    except Exception:
        pass


def _ensure_clean(proxy_db, proxy):
    try:
        proxy_db.remove(proxy)
    except Exception:
        pass


def test_get_working_proxies_ssl_filter(proxy_db: ProxyDB):
    p1 = "10.254.254.1:1111"
    p2 = "10.254.254.2:2222"
    p3 = "10.254.254.3:3333"

    # Ensure clean start
    for p in (p1, p2, p3):
        _ensure_clean(proxy_db, p)

    try:
        # Add proxies and mark active with different https values
        proxy_db.add(p1)
        proxy_db.add(p2)
        proxy_db.add(p3)

        proxy_db.update_data(p1, {"status": "active", "https": "true"})
        proxy_db.update_data(p2, {"status": "active", "https": "false"})
        proxy_db.update_data(p3, {"status": "active", "https": ""})

        # SSL-only
        ssl_only = proxy_db.get_working_proxies(limit=50, randomize=False, ssl=True)
        assert isinstance(ssl_only, list)
        assert any(r.get("proxy") == p1 for r in ssl_only)
        assert all((r.get("https") == "true") for r in ssl_only)

        # Non-SSL (false or empty)
        non_ssl = proxy_db.get_working_proxies(limit=50, randomize=False, ssl=False)
        assert isinstance(non_ssl, list)
        found_p2 = any(r.get("proxy") == p2 for r in non_ssl)
        found_p3 = any(r.get("proxy") == p3 for r in non_ssl)
        assert found_p2 or found_p3

        # No filter: should include all three
        all_proxies = proxy_db.get_working_proxies(limit=50, randomize=False, ssl=None)
        assert any(r.get("proxy") == p1 for r in all_proxies)
        assert any(r.get("proxy") == p2 for r in all_proxies)
        assert any(r.get("proxy") == p3 for r in all_proxies)
    finally:
        # Cleanup
        for p in (p1, p2, p3):
            _ensure_clean(proxy_db, p)


if __name__ == "__main__":
    pytest.main(["-vvv", "-s", __file__])
