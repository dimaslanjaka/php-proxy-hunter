import requests
from urllib.parse import urlparse
import os

# Use the project's file writer utility to persist debug output
from proxy_hunter.utils.file.writer import write_file
from datetime import datetime, timezone, timedelta

try:
    # Python 3.9+
    from zoneinfo import ZoneInfo

    TimeZone = ZoneInfo("Asia/Jakarta")
except Exception:
    # Fallback to fixed offset +07:00 (Asia/Jakarta)
    TimeZone = timezone(timedelta(hours=7))


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
    # Cache sessions by output_file so the same log file reuses same Session
    _instances: dict[str, "DebugSession"] = {}

    def __new__(cls, output_file: str | None = None, echo: bool | None = True):
        # Only cache when an output_file is provided.
        key = None
        if output_file:
            try:
                # Normalize to absolute, case-normalized path for Windows
                key = os.path.abspath(output_file)
                key = os.path.normcase(key)
            except Exception:
                key = output_file

            existing = cls._instances.get(key)
            if existing is not None:
                return existing

        inst = super().__new__(cls)
        # store the normalized key on the new instance for __init__ registration
        setattr(inst, "_output_file_key", key)
        return inst

    def __init__(self, output_file: str | None = None, echo: bool | None = True):
        """Session that can echo raw HTTP to stdout and/or append to a file.

        Args:
            output_file: path to a file to append debug output to. If None, no file is written.
            echo: if True, prints debug output to stdout.

        Note: timestamps are always written automatically before each request/response block.
        """
        # Avoid re-initializing when returning cached instance from __new__
        if getattr(self, "_initialized", False):
            # If reusing an existing session, only update echo when caller
            # provided an explicit non-None value for echo.
            if echo is not None:
                self.echo = echo
            return

        super().__init__()
        self.output_file = output_file
        # If caller passed None, default to True for new sessions
        self.echo = True if echo is None else echo

        # Register in cache if an output_file was provided
        key = getattr(self, "_output_file_key", None)
        if not key and self.output_file:
            try:
                key = os.path.normcase(os.path.abspath(self.output_file))
            except Exception:
                key = self.output_file

        if key:
            try:
                self.__class__._instances[key] = self
            except Exception:
                pass

        self._initialized = True

    def _write_output(self, text: str):
        # Print to console if requested
        if self.echo:
            print(text)

        # Persist to file via project's write_file helper (best-effort)
        if self.output_file:
            try:
                existing = ""
                if os.path.exists(self.output_file):
                    try:
                        with open(self.output_file, "r", encoding="utf-8") as f:
                            existing = f.read()
                    except Exception:
                        existing = ""

                new_content = existing + text
                if not new_content.endswith("\n"):
                    new_content += "\n"

                # write_file will create parent folders and write the content
                write_file(self.output_file, new_content)
            except Exception:
                # Avoid raising from debug logging; best-effort only
                pass

    def send(self, request, **kwargs):
        # Always prefix with RFC3339 timestamp in Asia/Jakarta timezone
        now = datetime.now(TimeZone).isoformat()
        prefix = f"[{now}] "

        req_text = f"{prefix}=== RAW HTTP REQUEST ===\n" + format_raw_request(request)
        self._write_output(req_text)

        response = super().send(request, **kwargs)

        resp_text = f"{prefix}=== RAW HTTP RESPONSE ===\n" + format_raw_response(
            response
        )
        self._write_output(resp_text)

        return response


# === USAGE EXAMPLE ===
if __name__ == "__main__":
    # Example: write debug output to file and still print to stdout
    session = DebugSession(output_file="tmp/debug_http.log", echo=True)

    url = "https://httpbin.org/post"
    headers = {
        "X-Custom-Header": "CustomValue",
        "User-Agent": "PythonDebugClient/1.0",
        "Content-Type": "application/json",
        "Connection": "close",
    }

    payload = {"example": "data", "number": 123, "active": True}

    session.post(url, headers=headers, json=payload)
