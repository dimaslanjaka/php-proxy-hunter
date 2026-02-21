import os
import sys

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import certifi
import pytest
from requests import Response

from proxy_hunter.curl.request_helper import build_request


def do_request(**kwargs):
    try:
        return build_request(endpoint="https://www.example.com", **kwargs)
    except Exception:
        return None


@pytest.mark.parametrize("proxy_type", ["http", "socks4", "socks5"])
def test_with_proxy(proxy_type):
    proxy = "176.120.32.135:5678"
    response = do_request(proxy=proxy, proxy_type=proxy_type, verify=certifi.where())
    if response is None:
        pytest.skip(f"Proxy {proxy_type} at {proxy} is not working or unreachable.")
    assert isinstance(response, Response)
    assert response.status_code == 200
    assert "<title>Example Domain</title>" in response.text


if __name__ == "__main__":
    sys.exit(pytest.main([__file__]))
