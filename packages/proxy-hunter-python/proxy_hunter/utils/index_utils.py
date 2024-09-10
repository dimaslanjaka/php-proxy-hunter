import base64
import gzip
import hashlib
import inspect
import ipaddress
import platform
import random
import re
import subprocess
import zlib
from io import BytesIO
from typing import Optional, Dict, Tuple, List, Any, Union, TypeVar
from urllib.parse import urlparse

import brotli
import chardet
import requests


def is_valid_url(url: str) -> bool:
    """
    Check if the given URL is valid.

    Args:
        url (str): The URL to be validated.

    Returns:
        bool: True if the URL is valid, False otherwise.
    """
    try:
        parsed_url = urlparse(url)
        # A URL is considered valid if it has a scheme and netloc
        return bool(parsed_url.scheme and parsed_url.netloc)
    except ValueError:
        return False


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


def is_vps() -> bool:
    """
    Check if the system is a Virtual Private Server (VPS) by looking for virtualization indicators.

    Returns:
        bool: True if the system is a VPS, False if it is a physical machine or if the check fails.
    """
    if platform.system() != "Linux":
        print("This check is only applicable for Linux systems.")
        return False

    try:
        output = subprocess.check_output(["lscpu"]).decode()
        return "Hypervisor" in output
    except Exception as e:
        print(f"Error: {e}")
        return False


if __name__ == "__main__":
    print(is_valid_url("google"))
    print(is_valid_url("google.com"))
    print(is_valid_url("http://google.com"))
    print(is_valid_url("https://google.com"))
    print(is_valid_url("https://google.com:8000"))
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


def decompress_requests_response(
    response: requests.Response, debug: bool = False
) -> str:
    """
    Decompresses the content of a requests response object if it's compressed.

    Args:
        response (requests.Response): The response object from a requests call.
        debug (bool): Whether to print debugging information.

    Returns:
        str: The decompressed response content as a string.
    """
    # Ensure content is of type bytes
    content: bytes = response.content  # type: ignore

    # Check if the response has content encoding
    encoding = response.headers.get("Content-Encoding", "").lower()

    try:
        if encoding == "gzip":
            # Handle gzip encoding
            buf = BytesIO(content)
            with gzip.GzipFile(fileobj=buf) as f:
                content = f.read()
        elif encoding == "deflate":
            # Handle deflate encoding
            content = zlib.decompress(content, -zlib.MAX_WBITS)
        elif encoding == "br":
            # Handle Brotli encoding
            content = brotli.decompress(content)
        else:
            # No encoding or unsupported encoding
            pass
    except (OSError, zlib.error, ValueError) as e:
        if debug:
            print(f"Decompression error: {e}")
        # Fallback to raw content if there's an error
        content = response.content  # type: ignore

    # Detect encoding if not specified or incorrectly detected
    detected_encoding = chardet.detect(content).get("encoding", "utf-8") or "utf-8"

    # Decode the content with detected encoding
    try:
        return content.decode(detected_encoding)
    except (UnicodeDecodeError, TypeError) as e:
        if debug:
            print(f"Decoding error: {e}")
        return content.decode(
            "utf-8", errors="replace"
        )  # Fallback to utf-8 with error handling


def is_class_has_parameter(clazz: type, key: str) -> bool:
    """
    Check if a class has a specified parameter in its constructor.

    Args:
        clazz (type): The class to inspect.
        key (str): The parameter name to check for.

    Returns:
        bool: True if the class has the specified parameter, False otherwise.
    """
    inspect_method = inspect.signature(clazz)
    return key in inspect_method.parameters


def get_random_dict(dictionary: Dict) -> Tuple:
    """
    Return a random key-value pair from the given dictionary.

    Parameters:
        dictionary (dict): The dictionary from which to select a random key-value pair.

    Returns:
        tuple: A tuple containing a random key and its corresponding value.
    """
    random_key = random.choice(list(dictionary.keys()))
    random_value = dictionary[random_key]
    return random_key, random_value


def keep_alphanumeric_and_remove_spaces(input_string: str) -> str:
    """
    Removes spaces and keeps only alphanumeric characters from the input string.

    Args:
    - input_string (str): The input string containing alphanumeric and non-alphanumeric characters.

    Returns:
    - str: The cleaned string containing only alphanumeric characters.
    """
    # Remove spaces
    input_string = input_string.replace(" ", "")

    # Keep only alphanumeric characters using regular expression
    input_string = re.sub(r"[^a-zA-Z0-9]", "", input_string)

    return input_string


def get_unique_dicts_by_key_in_list(
    dicts: List[Dict[str, str]], key: str
) -> List[Dict[str, str]]:
    """
    Returns a list of unique dictionaries from the input list of dictionaries based on a specified key.

    Args:
        dicts (List[Dict[str, str]]): The list of dictionaries to process.
        key (str): The key based on which uniqueness is determined.

    Returns:
        List[Dict[str, str]]: A list of unique dictionaries based on the specified key.

    Example:
        ```
        proxies: List[Dict[str, str]] = [{'proxy': 'proxy1'}, {'proxy': 'proxy2'}, {'proxy': 'proxy1'}, {'proxy': 'proxy3'}]
        unique_proxies = get_unique_dicts_by_key_in_list(proxies, 'proxy')
        print(unique_proxies)
        ```
    """
    unique_values = set()
    unique_dicts = []

    for d in dicts:
        value = d.get(key)
        if value not in unique_values:
            unique_values.add(value)
            unique_dicts.append(d)

    return unique_dicts


def md5(input_string: str) -> str:
    return hashlib.md5(input_string.encode()).hexdigest()


def clean_dict(d: Dict[str, Any]) -> Dict[str, Any]:
    """
    Remove keys from the dictionary where the value is empty (None or an empty string)
    or under zero (for numerical values).

    Args:
        d (Dict[str, Any]): The dictionary to be cleaned.

    Returns:
        Dict[str, Any]: A new dictionary with unwanted key-value pairs removed.
    """
    return {
        k: v
        for k, v in d.items()
        if (v not in [None, "", 0] and (isinstance(v, (int, float)) and v >= 0))
    }


def base64_encode(data: Union[str, bytes]) -> str:
    """
    Encodes a given string or bytes into Base64.

    Args:
        data (Union[str, bytes]): The data to encode. Can be a string or bytes.

    Returns:
        str: The Base64 encoded string.
    """
    if isinstance(data, str):
        data = data.encode("utf-8")  # Convert string to bytes if necessary
    return base64.b64encode(data).decode("utf-8")


def base64_decode(encoded_data: str) -> str:
    """
    Decodes a Base64 encoded string back to its original string.

    Args:
        encoded_data (str): The Base64 encoded string to decode.

    Returns:
        str: The decoded string.
    """
    decoded_bytes = base64.b64decode(encoded_data)
    return decoded_bytes.decode("utf-8")  # Assuming the original data was UTF-8 encoded


def unique_non_empty_strings(strings: Optional[List[Union[str, None]]]) -> List[str]:
    """
    Filter out non-string elements, empty strings, and None from the input list,
    and return a list of unique non-empty strings.

    Args:
        strings (List[Union[str, None]]): The list of strings to process.

    Returns:
        List[str]: A list of unique non-empty strings.
    """
    if not strings:
        return []
    unique_strings = set()
    for s in strings:
        if isinstance(s, str) and s not in ("", None):
            unique_strings.add(s)
    return list(unique_strings)


T = TypeVar("T")


def split_list_into_chunks(
    lst: List[T], chunk_size: Optional[int] = None, total_chunks: Optional[int] = None
) -> List[List[T]]:
    """
    Split a list into chunks either by a specified chunk size or into a specified number of chunks.

    Args:
        lst (List[T]): The list to be split into chunks.
        chunk_size (Optional[int]): The size of each chunk. If provided, the list is split into chunks of this size.
        total_chunks (Optional[int]): The number of chunks to split the list into. If provided, the list is split into this many chunks.

    Returns:
        List[List[T]]: A list of lists, where each inner list is a chunk of the original list.

    Raises:
        ValueError: If neither `chunk_size` nor `total_chunks` is provided.
    """
    if chunk_size is not None:
        # Split by specific chunk size
        return [lst[i : i + chunk_size] for i in range(0, len(lst), chunk_size)]

    elif total_chunks is not None:
        # Split into a specific number of chunks
        chunk_size = len(lst) // total_chunks
        remainder = len(lst) % total_chunks
        chunks = []
        start = 0

        for i in range(total_chunks):
            end = start + chunk_size + (1 if i < remainder else 0)
            chunks.append(lst[start:end])
            start = end

        return chunks

    else:
        raise ValueError("Either chunk_size or total_chunks must be provided.")


def get_random_item_list(arr: List[T]) -> T:
    random.shuffle(arr)
    return random.choice(arr)
