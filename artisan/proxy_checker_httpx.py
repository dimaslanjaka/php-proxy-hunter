import asyncio
import os
import ssl
import sys
import json
import re
import time
import httpx
import argparse
from httpx_socks import AsyncProxyTransport
from proxy_hunter import extract_proxies
from typing import Dict, Any, Optional
import urllib.parse
from dataclasses import dataclass, field

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path
from src.shared import init_db, init_readonly_db
from src.func_date import get_current_rfc3339_time
from src.utils.file.FileLockHelper import FileLockHelper
from src.ProxyDB import ProxyDB
from src.func_platform import is_debug

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None

TEST_HTTP = "http://httpbin.org/ip"
TEST_HTTPS = "https://httpbin.org/ip"
TIMEOUT = 8

PROTOCOLS = {
    "http": "http://{host}:{port}",
    "socks4": "socks4://{host}:{port}?rdns=true",  # SOCKS4a behavior
    "socks5": "socks5://{host}:{port}",  # local DNS
    "socks5h": "socks5://{host}:{port}?rdns=true",  # SOCKS5 with remote DNS (socks5h)
}

# Load CA bundle
cafile = get_relative_path("data/cacert.pem")
ssl_ctx = ssl.create_default_context(cafile=cafile)


@dataclass
class EndpointResult:
    """Result of a single endpoint (HTTP or HTTPS) test."""

    status: Optional[int] = None
    "status code returned by the endpoint"
    response: Optional[str] = None
    "raw response body as text"
    error: Optional[str] = None
    "error message if the request failed"
    ip: Optional[str] = None
    "origin IP reported by the test service"
    ok: bool = False
    "True if the request succeeded"
    latency: Optional[float] = None
    "latency in milliseconds"
    private: bool = False
    "True if the endpoint reported a private/internal IP or otherwise marked private"

    def to_dict(self) -> Dict[str, Any]:
        return {
            "status": self.status,
            "response": self.response,
            "error": self.error,
            "ip": self.ip,
            "ok": self.ok,
            "latency": self.latency,
            "private": self.private,
        }


@dataclass
class ProxyTestResult:
    """Aggregate result for a proxy and protocol."""

    http: EndpointResult = field(default_factory=EndpointResult)
    "result of the HTTP endpoint test"
    https: EndpointResult = field(default_factory=EndpointResult)
    "result of the HTTPS endpoint test"
    ssl: bool = False
    "True if HTTPS request succeeded (proxy supports SSL)"
    ok: bool = False
    "True if any endpoint succeeded for this proxy"
    proxy: Optional[str] = None
    "Host:port string of the tested proxy"
    latency: Optional[float] = None
    "average latency across available endpoint measurements in milliseconds"
    private: bool = False
    "True if any endpoint for this proxy is marked private"

    def to_dict(self) -> Dict[str, Any]:
        return {
            "http": self.http.to_dict(),
            "https": self.https.to_dict(),
            "ssl": self.ssl,
            "private": self.private,
            "proxy": self.proxy,
            "latency": self.latency,
        }

    def __str__(self) -> str:
        return json.dumps(self.to_dict())


async def test_proxy(proxy_string: str) -> Dict[str, ProxyTestResult]:
    results: Dict[str, ProxyTestResult] = {}
    proxy_data = extract_proxies(proxy_string)[0]

    host, port = proxy_data.proxy.split(":")
    username = proxy_data.username
    password = proxy_data.password

    # URL-encode credentials
    if username:
        username = urllib.parse.quote(username)
    if password:
        password = urllib.parse.quote(password)

    for proto, fmt in PROTOCOLS.items():
        # Build proxy URL
        proxy_url = fmt.format(host=host, port=port)
        if username and password:
            proto_prefix = proxy_url.split("://")[0]
            proxy_url = f"{proto_prefix}://{username}:{password}@{host}:{port}"

        result = ProxyTestResult()
        result.proxy = f"{host}:{port}"

        transport = AsyncProxyTransport.from_url(proxy_url)

        async with httpx.AsyncClient(
            transport=transport,
            timeout=TIMEOUT,
            verify=ssl_ctx,  # trust your CA bundle
        ) as client:

            # --- HTTP test ---
            try:
                t0 = time.monotonic()
                r = await client.get(TEST_HTTP)
                t1 = time.monotonic()

                result.http.latency = int(round((t1 - t0) * 1000.0))
                result.http.ok = True
                result.http.status = r.status_code
                result.http.response = r.text
                result.http.ip = r.json().get("origin")
            except Exception as e:
                result.http.error = str(e)
                if re.search(
                    r"No acceptable authentication methods were offered", str(e), re.I
                ):
                    result.http.private = True

            # --- HTTPS / SSL test ---
            try:
                t0 = time.monotonic()
                r = await client.get(TEST_HTTPS)
                t1 = time.monotonic()

                result.https.latency = int(round((t1 - t0) * 1000.0))
                result.https.ok = True
                result.ssl = True
                result.https.status = r.status_code
                result.https.response = r.text
                result.https.ip = r.json().get("origin")
            except Exception as e:
                result.https.error = str(e)
                if re.search(
                    r"No acceptable authentication methods were offered", str(e), re.I
                ):
                    result.https.private = True

        # Overall proxy status
        result.ok = result.http.ok or result.https.ok

        # Average latency
        latencies = [
            x for x in (result.http.latency, result.https.latency) if x is not None
        ]
        if latencies:
            result.latency = int(round(sum(latencies) / len(latencies)))
        else:
            result.latency = None

        # Mark proxy as private if any endpoint is private
        result.private = bool(result.http.private or result.https.private)

        # Store result
        results[proto] = result

    return results


def process_result(res: Dict[str, ProxyTestResult]) -> None:
    # Collect working protocols
    working = {p: info for p, info in res.items() if info.ok}
    working_protocols = list(working.keys())
    working_protocols_ssl = [p for p, info in working.items() if info.ssl]
    is_ssl = len(working_protocols_ssl) > 0

    # Pretty print results
    for proto, info in res.items():
        print(f"{proto.upper():8} {json.dumps(info.to_dict(), indent=2)}")

    if not working:
        print("  Proxy failed for all protocols")
        return

    # All ProxyTestResult share same proxy string
    sample = next(iter(working.values()))
    if not sample.proxy:
        print("  No proxy string in result, cannot update database.")
        return

    # Choose best latency (min of successful ones)
    latencies = [info.latency for info in working.values() if info.latency is not None]
    best_latency = int(min(latencies)) if latencies else None

    db = init_db("mysql")
    # Update database record
    # Prefer SSL-capable protocols for type field
    db.update_data(
        sample.proxy,
        {
            "last_check": get_current_rfc3339_time(),
            "status": "active" if working else "dead",
            "https": "true" if is_ssl else "false",
            "latency": str(best_latency) if best_latency is not None else "",
            "type": (
                "-".join(sorted(working_protocols_ssl))
                if is_ssl
                else "-".join(sorted(working_protocols))
            ),
        },
    )

    print(
        f"  Proxy {sample.proxy} OK: "
        f"protocols={','.join(working_protocols)} "
        f"latency={best_latency}ms"
    )


async def main(db: ProxyDB, non_ssl: bool = False) -> None:
    # Fetch proxies from database
    untested_proxies = db.get_untested_proxies(limit=1000)
    non_ssl_proxies = db.get_working_proxies(limit=1000, ssl=False)

    # Choose source list based on CLI flag: default to untested, or non-SSL when requested
    proxies_to_test = non_ssl_proxies if non_ssl else untested_proxies

    # If fewer than 1000 proxies, fill the remainder with dead proxies
    if len(proxies_to_test) < 1000:
        needed = 1000 - len(proxies_to_test)
        dead_proxies = db.get_dead_proxies(limit=needed)
        # Combine and trim to ensure exactly 1000 entries maximum
        proxies_to_test = (proxies_to_test + dead_proxies)[:1000]

    # If no proxies available, exit gracefully
    if not proxies_to_test:
        print("No proxies available for testing.")
        return

    # For now test the first proxy from the assembled list
    for data in proxies_to_test:
        proxy = data["proxy"]
        if not proxy:
            print(
                f"Empty proxy string from database id {data.get('id', 'unknown')}, skipping."
            )
            continue
        print(f"Testing proxy: {proxy}")
        res = await test_proxy(proxy)
        process_result(res)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Proxy checker (httpx).")
    parser.add_argument(
        "--readonly", action="store_true", help="Use readonly DB connection"
    )
    parser.add_argument(
        "--non-ssl", action="store_true", help="Use only non-SSL working proxies"
    )
    parser.add_argument("--uid", type=str, help="Override lock filename (unique id)")
    args = parser.parse_args()

    # Apply optional UID override for the lock filename
    current_filename = args.uid if args.uid else os.path.basename(__file__)

    # Create and acquire file lock after CLI parsing to allow overrides
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    db = init_readonly_db() if args.readonly else init_db("mysql")

    if is_debug():
        asyncio.run(main(db, args.non_ssl))
    else:
        try:
            asyncio.run(main(db, args.non_ssl))
        except Exception as e:
            print(f"Unhandled exception: {e}")
    if locker:
        locker.unlock()
