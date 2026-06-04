from __future__ import annotations

import time
from dataclasses import dataclass
from typing import Optional, Union

import httpx

VerifyType = Union[bool, str]  # True, False, or path to PEM file


@dataclass
class ProxyCheckHTTPXResult:
    """Result of a proxy check via httpx."""

    proxy: Optional[str] = None
    status_code: Optional[int] = None
    protocol: Optional[str] = None
    latency: Optional[float] = None
    response: Optional[httpx.Response] = None
    error: Optional[str] = None

    @property
    def ok(self) -> bool:
        """Return True if request succeeded without error and response exists."""
        return self.error is None and self.response is not None


async def check_http_proxy(
    proxy: str,
    endpoint: str,
    *,
    timeout: float = 10.0,
    verify: VerifyType = True,
) -> ProxyCheckHTTPXResult:
    """Check HTTP proxy with optional custom SSL certificate."""
    proxy_url = f"http://{proxy}"
    start = time.monotonic()

    try:
        async with httpx.AsyncClient(
            proxy=proxy_url,
            timeout=timeout,
            follow_redirects=True,
            verify=verify,
        ) as client:
            response = await client.get(endpoint)

        return ProxyCheckHTTPXResult(
            proxy=proxy,
            status_code=response.status_code,
            protocol="http",
            latency=(time.monotonic() - start) * 1000,
            response=response,
        )

    except Exception as e:
        return ProxyCheckHTTPXResult(
            proxy=proxy,
            protocol="http",
            latency=(time.monotonic() - start) * 1000,
            error=str(e),
        )


async def check_socks5_proxy(
    proxy: str,
    endpoint: str,
    *,
    timeout: float = 10.0,
    verify: VerifyType = True,
) -> ProxyCheckHTTPXResult:
    """Check SOCKS5 proxy with optional custom SSL certificate."""
    proxy_url = f"socks5://{proxy}"
    start = time.monotonic()

    try:
        async with httpx.AsyncClient(
            proxy=proxy_url,
            timeout=timeout,
            follow_redirects=True,
            verify=verify,
        ) as client:
            response = await client.get(endpoint)

        return ProxyCheckHTTPXResult(
            proxy=proxy,
            status_code=response.status_code,
            protocol="socks5",
            latency=(time.monotonic() - start) * 1000,
            response=response,
        )

    except Exception as e:
        return ProxyCheckHTTPXResult(
            proxy=proxy,
            protocol="socks5",
            latency=(time.monotonic() - start) * 1000,
            error=str(e),
        )
