import gzip
import ipaddress
import re
import zlib
from io import BytesIO
from typing import Optional
import brotli
import chardet
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
                content = f.read()
        elif encoding == "deflate":
            # Handle deflate encoding
            content = zlib.decompress(response.content, -zlib.MAX_WBITS)
        elif encoding == "br":
            # Handle Brotli encoding
            content = brotli.decompress(response.content)
        else:
            # No encoding or unsupported encoding
            content = response.content
    except (OSError, zlib.error, Exception) as e:
        # Handle errors in decompression (including Brotli errors)
        print(f"Decompression error: {e}")
        content = response.content  # Fallback to raw content

    # Detect encoding if not specified or incorrectly detected
    detected_encoding = chardet.detect(content).get("encoding")

    if detected_encoding is None:
        detected_encoding = "utf-8"  # Fallback to a default encoding

    # Decode the content with detected encoding
    try:
        return content.decode(detected_encoding)
    except (UnicodeDecodeError, TypeError) as e:
        # Handle decoding errors
        print(f"Decoding error: {e}")
        return content.decode(
            "utf-8", errors="replace"
        )  # Fallback to utf-8 with error handling


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

    return is_ip_valid and not ip.startswith("0")


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

    # Handle credentials if present
    has_credential = "@" in proxy
    if has_credential:
        try:
            proxy, credential = proxy.strip().split("@", 1)
            username, password = (credential.strip().split(":", 1) + [None, None])[:2]
            if validate_credential and (not username or not password):
                return False
        except ValueError:
            return False  # Invalid credentials format

    # Extract IP address and port
    parts = proxy.strip().split(":", 1)
    if len(parts) != 2:
        return False

    ip, port = parts

    # Validate IP address (using provided function)
    if not is_valid_ip(ip):
        return False

    # Validate port number
    try:
        port_int = int(port)
        if not (1 <= port_int <= 65535):
            return False
    except ValueError:
        return False

    # Check if the proxy string length is appropriate (if applicable)
    proxy_length = len(proxy)
    if not (7 <= proxy_length <= 21):  # Adjust based on valid range
        return False

    return True


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
    print(f"Is valid: {is_valid_proxy('801.0.0.10:801')}")  # Invalid IP
    print(f"Is valid: {is_valid_proxy('0.228.156.97:80')}")  # Check this case
    print(
        f"Is valid: {is_valid_proxy('192.168.1.1:8080')}"
    )  # Local IP with a standard port
    print(
        f"Is valid: {is_valid_proxy('255.255.255.255:65535')}"
    )  # Max values for IP and port
    print(f"Is valid: {is_valid_proxy('999.999.999.999:80')}")  # Invalid IP
    print(f"Is valid: {is_valid_proxy('192.168.1.1:99999')}")  # Invalid port
    print(f"Is valid: {is_valid_proxy('192.168.1.1')}")  # Missing port
    print(f"Is valid: {is_valid_proxy('0.0.0.0:0')}")  # Port out of valid range
    print(
        f"Is valid: {is_valid_proxy('192.168.1.1:80@user:pass')}"
    )  # Valid proxy with credentials
    print(
        f"Is valid: {is_valid_proxy('192.168.1.1:80@user:')}"
    )  # Invalid proxy with incomplete credentials
