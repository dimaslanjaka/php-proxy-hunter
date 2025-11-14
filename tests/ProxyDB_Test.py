import os
import sys
import pytest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.ProxyDB import ProxyDB
from dotenv import find_dotenv, load_dotenv


@pytest.fixture(scope="session", autouse=True)
def load_env() -> None:
    env_file = find_dotenv(filename=".env", usecwd=True)
    print(f"Loading env file: {env_file}")
    load_dotenv(env_file)


@pytest.fixture(scope="module")
def proxy_db():
    db = ProxyDB(start=True)
    yield db
    # Teardown: close the ProxyDB connection after tests
    try:
        close_fn = getattr(db, "close", None) or getattr(
            getattr(db, "db", None), "close", None
        )
        if callable(close_fn):
            close_fn()
    except Exception:
        pass


def test_vacuum(proxy_db: ProxyDB):
    if proxy_db.db:
        proxy_db.db.execute_query("PRAGMA journal_mode = WAL")
        proxy_db.db.execute_query("PRAGMA wal_autocheckpoint = 100")
        proxy_db.db.execute_query("PRAGMA auto_vacuum = FULL")
        proxy_db.db.execute_query("VACUUM")
        # https://stackoverflow.com/a/37865221/6404439
        proxy_db.db.execute_query("PRAGMA wal_checkpoint(SQLITE_CHECKPOINT_TRUNCATE);")


def test_get_all(proxy_db):
    all_p = proxy_db.get_all_proxies(True)[:10]
    assert len(all_p) == 10


def test_update(proxy_db):
    proxy = "13.208.56.180:80"
    proxy_type = "http"
    items = proxy_db.select(proxy)
    if items:
        item = items[0]
    else:
        item = proxy_db.add(proxy)[0]
    if not item.get("type"):
        # fix missing proxy type
        item["type"] = proxy_type
    item = proxy_db.fix_empty_single_data(item)
    print("before", item)
    # city should not be None
    assert item.get("latitude", None) is not None
    proxy_db.update_data(proxy, {"latitude": None})
    reselect = proxy_db.select(proxy)[0]
    print("after", reselect)
    # city should None
    assert reselect.get("latitude", None) is None


if __name__ == "__main__":
    pytest.main(["-vvv", "-s", __file__])
