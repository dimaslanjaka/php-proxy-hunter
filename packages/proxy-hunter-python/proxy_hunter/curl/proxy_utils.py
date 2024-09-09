import re
import socket
import threading
from typing import Union, Dict, Any, Callable, Optional
from urllib.parse import urlparse

import certifi
import requests

from proxy_checker import ProxyChecker
from proxy_hunter.curl.func_useragent import get_pc_useragent
from proxy_hunter.curl.request_helper import build_request
from proxy_hunter.utils.regex_utils import find_substring_from_regex


def get_device_ip() -> Union[None, str]:
    ip_services = [
        "https://api64.ipify.org",
        "https://ipinfo.io/ip",
        "https://api.myip.com",
        "https://ip.42.pl/raw",
        "https://ifconfig.me/ip",
        "https://cloudflare.com/cdn-cgi/trace",
        "https://httpbin.org/ip",
        "https://api.ipify.org",
    ]
    for url in ip_services:
        response = build_request(endpoint=url)
        if response and response.ok:
            # parse IP using regex
            ip_address_match = re.search(
                r"(?!0)(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)",
                response.text,
            )
            if ip_address_match:
                return ip_address_match.group(0)
    return None


class ProxyCheckResult:
    def __init__(
        self,
        result: bool,
        latency: Union[float, int],
        error: str,
        status: Union[int, None],
        private: bool,
        response: requests.Response = None,
        proxy: str = None,
        type: str = None,
        url: str = None,
        https: bool = None,
        additional: Dict[str, Any] = {},
    ):
        self.result = result
        self.latency = latency
        self.error = error
        self.status = status
        self.private = private
        self.certificate = certifi.where()
        self.response = response
        self.proxy = proxy
        self.type = type
        self.https = https
        self.url = url
        self.additional = additional

    def __str__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"

    def __repr__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"


def check_proxy(
    proxy: str,
    proxy_type: str,
    endpoint: str = None,
    headers: Dict[str, str] = None,
    callback: Callable[[ProxyCheckResult], None] = None,
    cancel_event: Optional[threading.Event] = None,
) -> ProxyCheckResult:
    """
    Checks if the provided proxy is working by sending a request.

    Args:
        proxy (str): The proxy address in the format IP:PORT.
        proxy_type (str): Type of proxy ('http', 'socks4', or 'socks5').
        callback (Callable[[ProxyCheckResult], None]): Callback function to execute before returning the result.
        endpoint (str, optional): The endpoint URL for the request. Defaults to None.
        headers (Dict[str, str], optional): Headers for the request. Defaults to None.
        cancel_event (threading.Event, optional): Event to signal cancellation. Defaults to None.

    Returns:
        ProxyCheckResult: An object containing the result of the check.
    """
    default_headers = {"User-Agent": get_pc_useragent()}
    if headers is not None:
        default_headers.update(headers)
    endpoint = endpoint or "https://httpbin.org/headers"
    latency = -1
    result = False
    is_private = False
    status = None
    error = None
    response = None

    if is_port_open(proxy):
        try:
            if cancel_event and cancel_event.is_set():
                return ProxyCheckResult(
                    result=False,
                    latency=latency,
                    error="Operation cancelled",
                    status=status,
                    private=is_private,
                    response=response,
                    proxy=proxy,
                    type=proxy_type,
                )

            response = build_request(
                proxy,
                proxy_type,
                method="GET",
                endpoint=endpoint,
                headers=default_headers,
            )
            latency = response.elapsed.total_seconds() * 1000  # in milliseconds
            is_private = (
                "X-Forwarded-For:" in response.headers
                or "Proxy-Authorization:" in response.headers
            )
            status = response.status_code
            valid_status_codes = [200, 201, 202, 204, 301, 302, 304]
            result = response.status_code in valid_status_codes
        except Exception as e:
            error = get_requests_error(e)
            pass
    else:
        proxy_ip, proxy_port = proxy.split(":")
        error = f"{proxy_ip} port {proxy_port} closed"

    if response is not None:
        current = urlparse(response.url)
        original = urlparse(endpoint)
        if current.hostname != original.hostname:
            # the proxy is private
            result = False
            is_private = True

    if not result:
        try:
            checker = ProxyChecker()
            lib_result = checker.check_proxy(proxy)
            if lib_result and proxy_type in lib_result["protocols"]:
                result = True
        except Exception:
            pass

    c_result = ProxyCheckResult(
        result=result,
        latency=latency,
        error=error,
        status=status,
        private=is_private,
        response=response,
        proxy=proxy,
        type=proxy_type,
    )

    # Execute the callback before returning the result
    if callback is not None and callable(callback):
        callback(c_result)

    return c_result


def get_requests_error(
    e: Union[Dict, Exception], return_default_error_message: bool = True
) -> Optional[str]:
    """
    Parses and extracts error information from requests-related exceptions.

    Args:
        e (Union[Dict, Exception]): The exception object or dictionary containing error information.
        return_default_error_message (bool, optional): Flag indicating whether to return the default error message
            if no specific error is detected. Defaults to True.

    Returns:
        Optional[str]: A descriptive error message extracted from the exception, or None if no error is detected
            and return_default_error_message is False.
    """

    get_socks = find_substring_from_regex(
        r"SOCKS\d proxy server sent invalid data", str(e)
    )
    proxy_timed_out = find_substring_from_regex(
        r"\b(?:\d{1,3}\.){3}\d{1,3}\b timed out", str(e)
    )
    domain_timed_out = find_substring_from_regex(r"\b(?:\w+\.)+\w+\b timed out", str(e))
    connection_refused = find_substring_from_regex(
        r"the target machine actively refused it", str(e)
    )
    connection_read_timeout = find_substring_from_regex(r"Read timed out", str(e))
    connection_closed = find_substring_from_regex(
        r"Connection closed unexpectedly", str(e)
    )
    connection_remote_closed = find_substring_from_regex(
        r"connection was forcibly closed by the remote host", str(e)
    )
    socks_credential_missing = find_substring_from_regex(
        r"SOCKS\d authentication methods were rejected", str(e)
    )
    connection_proxy_failed = find_substring_from_regex(
        r"Cannot connect to proxy", str(e)
    )
    connection_aborted = find_substring_from_regex(
        r"Connection aborted.*BadStatusLine", str(e)
    )
    if get_socks:
        error = get_socks
    elif socks_credential_missing:
        error = f"{socks_credential_missing} (Private)"
    elif connection_aborted:
        error = "ERR_CONNECTION_ABORTED"
    elif connection_refused:
        error = "ERR_CONNECTION_REFUSED"
    elif connection_proxy_failed:
        error = "ERR_PROXY_CONNECTION_FAILED"
    elif connection_closed:
        error = "ERR_CONNECTION_CLOSED"
    elif connection_remote_closed:
        error = "ERR_CONNECTION_CLOSED by remote host"
    elif domain_timed_out:
        error = domain_timed_out
    elif connection_read_timeout:
        error = connection_read_timeout
    elif proxy_timed_out:
        error = proxy_timed_out
    elif return_default_error_message:
        error = str(e)
    else:
        error = None
    return error


def is_port_open(address: str) -> bool:
    """
    Check if a port is open on a given IP address and port number.

    Args:
        address (str): A string in the format "IP:PORT".

    Returns:
        bool: True if the port is open, False otherwise.
    """
    result = False
    s = None
    try:
        # Split the address into IP and port
        host, port = address.split(":")
        port = int(port)  # Convert port to an integer
        # Create a new socket
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        # Set a timeout to prevent hanging
        s.settimeout(10)
        # Try to connect to the host and port
        s.connect((host, port))
        # debug_log(f"{address} {green('port open')}")
        # If successful, the port is open
        result = True
    except Exception as e:
        # log_proxy(f"{address} {red('port closed')} {str(e)}")
        result = False
    finally:
        if s:
            # Close the socket
            s.close()
    return result
