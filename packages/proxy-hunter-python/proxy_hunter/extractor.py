import re
from typing import List, Optional, Tuple, Union

from urlextract import URLExtract

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


def extract_proxies(string: Optional[str]) -> List[Proxy]:
    """
    Extracts IP:PORT pairs from a string, along with optional username and password.

    Args:
        string (Optional[str]): The input string containing IP:PORT pairs.
        write_database (Optional[bool]): Optional flag to determine if the results should be written to the database.

    Returns:
        List[Proxy]: A list containing the extracted IP:PORT pairs along with username and password if present.
    """
    if not string or not string.strip():
        return []

    results: List[Proxy] = []

    # Regular expression pattern to match IP:PORT pairs along with optional username and password
    pattern = r"((?:(?:\d{1,3}\.){3}\d{1,3})\:\d{2,5}(?:@\w+:\w+)?|(?:(?:\w+)\:\w+@\d{1,3}(?:\.\d{1,3}){3}\:\d{2,5}))"

    # Perform the matching IP:PORT
    matches1 = re.findall(pattern, string)

    # Perform the matching IP PORT (whitespaces)
    re_whitespace = r"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})"
    matches2 = re.findall(re_whitespace, string)
    matched_whitespaces = bool(matches2)

    # Perform the matching IP PORT (json) to match "ip":"x.x.x.x","port":"xxxxx"
    pattern_json = (
        r'"ip":"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})".*?"port":"((?!0)\d{2,5})'
    )
    matches3 = re.findall(pattern_json, string)
    matched_json = bool(matches3)

    # Merge matches
    matches = matches1 + matches2 + matches3

    for match in matches:
        if isinstance(match, tuple):
            match = match[0]

        if matched_whitespaces and len(match.split(":")) == 2:
            # print("matches whitespaces")
            ip, port = match.split(":")
            if is_valid_ip(ip):
                proxy = f"{ip}:{port}"
                if is_valid_proxy(proxy):
                    results.append(Proxy(proxy))
            continue

        if matched_json and len(match.split(":")) == 2:
            # print("matches json")
            ip, port = match.split(":")
            if is_valid_ip(ip):
                proxy = f"{ip}:{port}"
                if is_valid_proxy(proxy):
                    results.append(Proxy(proxy))
            continue

        if "@" in match:
            proxy, login = match.split("@")
            # print("has username and password")
            if is_valid_proxy(proxy):
                username, password = (login.split(":") + [None, None])[:2]
                result = Proxy(proxy=proxy, username=username, password=password)
                results.append(result)
        else:
            result = Proxy(match)
            results.append(result)

    # Unique list of proxy
    # Create a dictionary to prioritize proxies with credentials
    unique_proxies = {}

    for p in results:
        if p.proxy in unique_proxies:
            # If a proxy with the same address exists, prioritize the one with credentials
            if p.has_credentials():
                unique_proxies[p.proxy] = p
        else:
            unique_proxies[p.proxy] = p

    # Get a list of unique Proxy objects
    unique_proxy_list = list(unique_proxies.values())

    return unique_proxy_list


def extract_proxies_from_file(filename: str) -> List[Proxy]:
    """
    Read a file containing IP:PORT pairs and parse them.

    Args:
        filename (str): The path to the file.

    Returns:
        List[Proxy]: A list of parsed IP:PORT pairs.
    """
    proxies = []
    try:
        with open(filename, "r", encoding="utf-8") as file:
            for line in file:
                proxies.extend(extract_proxies(line))
    except Exception as e:
        print(f"fail open {filename} {str(e)}")
        pass
    return proxies


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


if __name__ == "__main__":
    proxies = extract_proxies(
        """
162.223.116.54:80@u:s
"""
    )
    print(proxies)
