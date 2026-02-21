import os
import sys
import pytest

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter.curl.request_helper import build_request


def do_request(**kwargs):
    try:
        return build_request(endpoint="https://www.example.com", **kwargs)
    except Exception:
        return None


def test_without_proxy():
    response = do_request()
    assert response is not None, "No response returned"
    assert response.status_code == 200


def test_post_formdata():
    url = "https://httpbin.org/post"
    data = {"foo": "bar", "baz": "qux"}
    response = build_request(endpoint=url, method="POST", post_data=data)
    assert response is not None
    assert response.status_code == 200
    json_resp = response.json()
    assert json_resp["form"] == data


def test_post_json():
    url = "https://httpbin.org/post"
    data = {"foo": "bar", "baz": "qux"}
    headers = {"Content-Type": "application/json"}
    response = build_request(
        endpoint=url, method="POST", post_data=data, headers=headers
    )
    assert response is not None
    assert response.status_code == 200
    json_resp = response.json()
    assert json_resp["json"] == data


if __name__ == "__main__":
    sys.exit(pytest.main([__file__]))
