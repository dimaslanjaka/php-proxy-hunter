import asyncio
import os
import ssl
import sys
import json
import time
import httpx
from httpx_socks import AsyncProxyTransport
from proxy_hunter import extract_proxies
from typing import Dict, Any, Optional
from dataclasses import dataclass, field

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path
from src.shared import init_db
from src.func_date import get_current_rfc3339_time
from src.utils.file.FileLockHelper import FileLockHelper
from src.func_platform import is_debug

locker = FileLockHelper(get_relative_path("tmp/locks/proxy-checker-httpx.lock"))
if not locker.lock():
    print("Another instance is running. Exiting.")
    sys.exit(0)

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

    def to_dict(self) -> Dict[str, Any]:
        return {
            "status": self.status,
            "response": self.response,
            "error": self.error,
            "ip": self.ip,
            "ok": self.ok,
            "latency": self.latency,
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

    def to_dict(self) -> Dict[str, Any]:
        return {
            "http": self.http.to_dict(),
            "https": self.https.to_dict(),
            "ssl": self.ssl,
            "proxy": self.proxy,
            "latency": self.latency,
        }

    def __str__(self) -> str:
        return json.dumps(self.to_dict())


async def test_proxy(proxy_string: str) -> Dict[str, ProxyTestResult]:
    results: Dict[str, ProxyTestResult] = {}
    host, port = extract_proxies(proxy_string)[0].proxy.split(":")

    for proto, fmt in PROTOCOLS.items():
        proxy_url = fmt.format(host=host, port=port)
        result = ProxyTestResult()
        result.proxy = f"{host}:{port}"

        transport = AsyncProxyTransport.from_url(proxy_url)

        async with httpx.AsyncClient(
            transport=transport,
            timeout=TIMEOUT,
            verify=ssl_ctx,  # trust your CA bundle
        ) as client:

            # HTTP test
            try:
                t0 = time.monotonic()
                r = await client.get(TEST_HTTP)
                t1 = time.monotonic()
                result.http.latency = (t1 - t0) * 1000.0
                result.http.ok = True
                result.http.status = r.status_code
                result.http.response = r.text
                result.http.ip = r.json().get("origin")
            except Exception as e:
                result.http.error = str(e)

            # HTTPS / SSL test
            try:
                t0 = time.monotonic()
                r = await client.get(TEST_HTTPS)
                t1 = time.monotonic()
                result.https.latency = (t1 - t0) * 1000.0
                result.https.ok = True
                result.ssl = True
                result.https.status = r.status_code
                result.https.response = r.text
                result.https.ip = r.json().get("origin")
            except Exception as e:
                result.https.error = str(e)

        # Overall proxy status
        result.ok = result.http.ok or result.https.ok
        # Compute average latency (ms) from available endpoint latencies
        _latencies = [
            x
            for x in (
                result.http.latency,
                result.https.latency,
            )
            if x is not None
        ]
        result.latency = sum(_latencies) / len(_latencies) if _latencies else None
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


async def main():
    db = init_db("mysql")
    untested_proxies = db.get_untested_proxies(limit=1000)

    # If fewer than 1000 untested proxies, fill the remainder with dead proxies
    if len(untested_proxies) < 1000:
        needed = 1000 - len(untested_proxies)
        dead_proxies = db.get_dead_proxies(limit=needed)
        # Combine and trim to ensure exactly 1000 entries maximum
        untested_proxies = (untested_proxies + dead_proxies)[:1000]

    # If no proxies available, exit gracefully
    if not untested_proxies:
        print("No proxies available for testing.")
        return

    # For now test the first proxy from the assembled list
    for data in untested_proxies:
        proxy = data["proxy"]
        if not proxy:
            print(
                f"Empty proxy string from database id {data.get('id', 'unknown')}, skipping."
            )
            continue
        print(f"Testing proxy: {proxy}")
        res = await test_proxy(proxy)
        process_result(res)


asyncio.run(main())
