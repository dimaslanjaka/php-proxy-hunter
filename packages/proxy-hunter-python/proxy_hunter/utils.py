import gzip
import ipaddress
import re
import zlib
from io import BytesIO
from typing import Optional

import requests


def decompress_requests_response(response: requests.Response) -> str:
    """
    Decompresses the content of a requests response object if it's compressed.

    Args:
        response (requests.Response): The response object from a requests call.

    Returns:
        str: The decompressed response content as a string.
    """
    # Check if the response has content encoding
    encoding = response.headers.get("Content-Encoding")

    try:
        if encoding == "gzip":
            # Handle gzip encoding
            buf = BytesIO(response.content)
            with gzip.GzipFile(fileobj=buf) as f:
                content = f.read().decode("utf-8")  # Adjust encoding if necessary
        elif encoding == "deflate":
            # Handle deflate encoding
            content = zlib.decompress(response.content, -zlib.MAX_WBITS).decode(
                "utf-8"
            )  # Adjust encoding if necessary
        else:
            # No encoding or unsupported encoding
            content = response.text  # Handles non-compressed responses
    except (OSError, zlib.error) as e:
        # Handle errors in decompression
        print(f"Decompression error: {e}")
        content = response.text  # Fallback to non-compressed response

    return content


def is_valid_ip_connection(proxy: Optional[str]) -> bool:
    if not proxy:
        return False

    split = proxy.strip().split(":", 1)
    ip = split[0]

    try:
        ipaddress.ip_address(ip)
        return True
    except ValueError:
        return False


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

    # Regex to validate IPv4 addresses
    is_ip_valid = (
        re.match(
            r"^(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9][0-9]?)\.(?:25[0-5]|2[0-4][0-9]|[0-1]?[0-9][0-9]?)$",
            ip,
        )
        is not None
    )

    return is_ip_valid


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
    is_proxy_valid = is_ip_valid and is_port_valid and 10 <= proxy_length <= 21

    # Validate credentials if required
    if has_credential and validate_credential:
        return is_proxy_valid and bool(username) and bool(password)

    return is_proxy_valid


def check_raw_headers_keywords(input_string: str) -> bool:
    """
    Check if at least 4 specific keywords are present in requests response.text.

    Parameters:
    input_string (str): The input string to be checked.

    Returns:
    bool: True if at least 4 of the specified keywords are found in the input string, False otherwise.
    """
    keywords = [
        "REMOTE_ADDR =",
        "REMOTE_PORT =",
        "REQUEST_METHOD =",
        "REQUEST_URI =",
        "HTTP_ACCEPT-LANGUAGE =",
        "HTTP_ACCEPT-ENCODING =",
        "HTTP_USER-AGENT =",
        "HTTP_ACCEPT =",
        "REQUEST_TIME =",
        "HTTP_UPGRADE-INSECURE-REQUESTS =",
        "HTTP_CONNECTION =",
        "HTTP_PRIORITY =",
    ]

    found_count = sum(1 for keyword in keywords if keyword in input_string)

    return found_count >= 4


if __name__ == "__main__":
    print(is_valid_proxy("801.0.0.10:801"))
