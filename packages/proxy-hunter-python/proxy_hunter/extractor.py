import re
from typing import List, Optional, Tuple, Union

from urlextract import URLExtract
from urllib.parse import urlparse

from .Proxy import Proxy
from .utils import is_valid_ip, is_valid_proxy


def extract_url(string: Optional[str]) -> List[str]:
    """
    Extracts valid HTTP/HTTPS URLs from a given string.

    Args:
        string (Optional[str]): The input string from which to extract URLs.

    Returns:
        List[str]: A list of unique extracted URLs. If no URLs are found or
                   the input is None, returns an empty list.
    """
    if not string:
        return []

    extractor = URLExtract()
    extracted_urls = extractor.find_urls(string)
    results: List[str] = []

    for item in extracted_urls:
        if isinstance(item, tuple):
            url = item[0]
        else:
            url = item

        if url.startswith("http://") or url.startswith("https://"):
            results.append(url)

    return list(set(results))  # Ensure unique results


def extract_ips(s: str) -> List[str]:
    """
    Extract all unique IP addresses from a given string.

    Args:
        s (str): The input string from which IP addresses will be extracted.

    Returns:
        List[str]: A list of unique IP addresses found in the string.
                   If no IP addresses are found, an empty list is returned.

    Example:
        >>> extract_ips("Here are some IP addresses: 192.168.0.1 and 10.0.0.1 and 192.168.0.1.")
        ['192.168.0.1', '10.0.0.1']
    """
    # Regular expression to match an IP address
    ip_pattern = r"\b(?:\d{1,3}\.){3}\d{1,3}\b"

    # Use re.findall to find all IP addresses in the string
    matches = re.findall(ip_pattern, s)

    # Return unique IP addresses in the order they were found
    return list(dict.fromkeys(matches))
