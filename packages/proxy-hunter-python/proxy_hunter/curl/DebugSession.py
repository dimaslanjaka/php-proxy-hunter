import requests
import http.client
from urllib.parse import urlparse
import json


def format_raw_request(prep: requests.PreparedRequest):
    parsed = urlparse(prep.url)
    path = parsed.path or "/"
    if isinstance(path, bytes):
        path = path.decode("utf-8")
    if parsed.query:
        path += f"?{parsed.query}"

    # Start with request line
    raw = f"{prep.method} {path} HTTP/1.1\n"

    # Add headers
    headers = prep.headers.copy()
    host = parsed.netloc
    if isinstance(host, bytes):
        host = host.decode("utf-8")
    headers["Host"] = host
    for k, v in headers.items():
        raw += f"{k}: {v}\n"

    raw += "\n"
    body = prep.body
    if isinstance(body, bytes):
        try:
            body = body.decode()
        except Exception:
            pass
    if body:
        raw += f"{body}\n"
    return raw


def format_raw_response(resp: requests.Response):
    raw = f"HTTP/1.1 {resp.status_code} {resp.reason}\n"
    for k, v in resp.headers.items():
        raw += f"{k}: {v}\n"
    raw += "\n"

    try:
        body = resp.text
    except Exception:
        body = "<non-text body>"
    raw += f"{body}\n"
    return raw


class DebugSession(requests.Session):
    def send(self, request, **kwargs):
        print("=== RAW HTTP REQUEST ===")
        print(format_raw_request(request))

        response = super().send(request, **kwargs)

        print("=== RAW HTTP RESPONSE ===")
        print(format_raw_response(response))

        return response


# === USAGE EXAMPLE ===
if __name__ == "__main__":
    session = DebugSession()

    url = "https://httpbin.org/post"
    headers = {
        "X-Custom-Header": "CustomValue",
        "User-Agent": "PythonDebugClient/1.0",
        "Content-Type": "application/json",
        "Connection": "close",
    }

    payload = {"example": "data", "number": 123, "active": True}

    session.post(url, headers=headers, json=payload)
