import gzip
import re
import zlib
from io import BytesIO
from typing import Optional

import requests


def decompress_requests_response(response: requests.Response):
    """
    Decompresses the content of a requests response object if it's compressed.

    Args:
        response (requests.Response): The response object from a requests call.

    Returns:
        str: The decompressed response content as a string.
    """
    # Check if the response has content encoding
    encoding = response.headers.get("Content-Encoding")

    if encoding == "gzip":
        # Handle gzip encoding
        buf = BytesIO(response.content)
        with gzip.GzipFile(fileobj=buf) as f:
            content = f.read().decode("utf-8")  # Adjust encoding if necessary
    elif encoding == "deflate":
        # Handle deflate encoding
        content = zlib.decompress(response.content, zlib.MAX_WBITS | 16).decode(
            "utf-8"
        )  # Adjust encoding if necessary
    else:
        # No encoding or unsupported encoding
        content = response.text  # Handles non-compressed responses

    return content


def is_valid_ip(proxy: Optional[str]) -> bool:
    """
    Validate a given proxy IP address.

    Args:
        proxy (Optional[str]): The proxy IP address to validate. Can be None.

    Returns:
        bool: True if the proxy IP address is valid, False otherwise.
    """
    if not proxy:
        return False

    split = proxy.strip().split(":", 1)
    ip = split[0]

    is_ip_valid = (
        re.match(r"^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$", ip) is not None
        and len(ip) >= 7
        and ".." not in ip
    )
    re_pattern = re.compile(r"(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}")

    return is_ip_valid and re_pattern.match(ip) is not None


def is_valid_proxy(proxy: Optional[str], validate_credential: bool = True) -> bool:
    """
    Validates a proxy string.

    Args:
        proxy (Optional[str]): The proxy string to validate.
        validate_credential (bool): Whether to validate credentials if present.

    Returns:
        bool: True if the proxy is valid, False otherwise.
    """
    if not proxy:
        return False

    username = None
    password = None
    has_credential = "@" in proxy

    # Extract username and password if credentials are present
    if has_credential:
        proxy, credential = proxy.strip().split("@", 1)
        username, password = (credential.strip().split(":", 1) + [None, None])[:2]

    # Extract IP address and port
    parts = proxy.strip().split(":", 1)
    if len(parts) != 2:
        return False

    ip, port = parts

    # Validate IP address
    is_ip_valid = is_valid_ip(ip)

    # Validate port number
    try:
        port_int = int(port)
        is_port_valid = 1 <= port_int <= 65535
    except ValueError:
        is_port_valid = False

    # Check if proxy is valid
    proxy_length = len(proxy)
    pattern = r"(?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:(?!0)\d{2,5}"
    is_proxy_valid = (
        is_ip_valid
        and is_port_valid
        and 10 <= proxy_length <= 21
        and re.fullmatch(pattern, proxy)
    )

    # Validate credentials if required
    if has_credential and validate_credential:
        return is_proxy_valid and bool(username) and bool(password)

    return is_proxy_valid
