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


def test_normalize_proxy(proxy_db: ProxyDB):
    # 44.226.21.44:0796  should be 44.226.21.44:796
    proxy = "44.226.21.44:0796"
    normalized_proxy = proxy_db.normalize_proxy(proxy)
    assert normalized_proxy == "44.226.21.44:796"
    # Test with leading zeros in IP octets as well
    proxy = "044.026.021.044:0796"
    normalized_proxy = proxy_db.normalize_proxy(proxy)
    assert normalized_proxy == "44.26.21.44:796"
    # Test with extra 177.26.112.65:5678: should be 177.26.112.65:5678
    proxy = "177.26.112.65:5678:"
    normalized_proxy = proxy_db.normalize_proxy(proxy)
    assert normalized_proxy == "177.26.112.65:5678"
    proxy = "103.250.166.04:6667:"
    normalized_proxy = proxy_db.normalize_proxy(proxy)
    assert normalized_proxy == "103.250.166.4:6667"
    # Test with missing port
    proxy = "177.26.112.65:"
    normalized_proxy = proxy_db.normalize_proxy(proxy)
    assert normalized_proxy == ""
    # Test with full url format
    proxy = "http://174.138.165.126:33508"
    normalized_proxy = proxy_db.normalize_proxy(proxy)
    assert normalized_proxy == "174.138.165.126:33508"


if __name__ == "__main__":
    pytest.main(["-vvv", "-s", __file__])
