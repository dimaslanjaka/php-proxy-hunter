from dataclasses import dataclass, field
from typing import Any, Dict, Optional, Union

import certifi
import requests


@dataclass
class ProxyCheckResult:
    """Result of a single proxy connectivity and capability check.

    Attributes:
        result: Whether the proxy successfully reached the target endpoint.
        latency: Response time in milliseconds (-1 if unavailable).
        error: Error message if the check failed, otherwise None.
        status: HTTP status code returned by the target (e.g. 200, 502).
        private: True if the proxy appears to require authentication.
        certificate: Path to the CA certificate bundle used for TLS
            verification (auto-populated via certifi).
        response: The raw requests.Response object from the check, if available.
        proxy: The proxy address that was tested (e.g. '127.0.0.1:8080').
        type: Proxy protocol type ('http', 'socks4', 'socks5', or None).
        url: The target URL the proxy was tested against.
        https: Whether the proxy supports HTTPS connections (True/False/None).
        additional: Free-form dict for any extra metadata from the check.
    """

    result: Optional[bool]
    latency: Union[float, int]
    error: Optional[str]
    status: Optional[int]
    private: bool
    certificate: str = field(default_factory=certifi.where)
    response: Optional[requests.Response] = None
    proxy: Optional[str] = None
    type: Optional[str] = None
    url: Optional[str] = None
    https: Optional[bool] = None
    additional: Optional[Dict[str, Any]] = None

    def __str__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"

    def __repr__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"
