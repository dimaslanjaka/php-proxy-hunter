import re
from typing import List, Optional
from urllib.parse import urlparse
from .regex_match import regex_match
from proxy_hunter.Proxy import Proxy
from proxy_hunter.utils import is_valid_ip, is_valid_proxy


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

    # Use the regex_match helper (covers user:pass@host:port, host:port@user:pass, host:port)
    regex_match_results = regex_match(string)
    for p in regex_match_results:
        results.append(p)

    # Perform the matching IP PORT (whitespaces) - support bracketed IPv6
    re_whitespace = r"(\[[0-9a-fA-F:]+\]|(?:\d{1,3}(?:\.\d{1,3}){3}))\s+((?!0)\d{2,5})"
    matches2 = re.findall(re_whitespace, string)

    # Perform the matching IP PORT (json) to match "ip":"x.x.x.x" or IPv6 and "port":"xxxxx"
    pattern_json = r'"ip"\s*:\s*"([^\"]+)"\s*,\s*"port"\s*:\s*"((?!0)\d{2,5})"'
    matches3 = re.findall(pattern_json, string)

    # Process whitespace matches (tuples of (ip, port))
    for ip, port in matches2:
        if is_valid_ip(ip):
            proxy = f"{ip}:{port}"
            if is_valid_proxy(proxy):
                results.append(Proxy(proxy))

    # Process JSON matches (tuples of (ip, port))
    for ip, port in matches3:
        if is_valid_ip(ip):
            proxy = f"{ip}:{port}"
            if is_valid_proxy(proxy):
                # Try to extract username/password from surrounding JSON if present
                user_m = re.search(r'"user"\s*:\s*"([^\"]+)"', string)
                pass_m = re.search(r'"pass"\s*:\s*"([^\"]+)"', string)
                if user_m and pass_m:
                    username = user_m.group(1)
                    password = pass_m.group(1)
                    results.append(
                        Proxy(proxy=proxy, username=username, password=password)
                    )
                else:
                    results.append(Proxy(proxy))

    # new method regex_match
    regex_match_results = regex_match(string)
    for p in regex_match_results:
        results.append(p)

    # Unique list of proxy
    # Use a dictionary keyed by proxy+username+password so different credentials
    # for the same host:port are preserved as distinct proxies.
    unique_proxies = {}

    for p in results:
        key = f"{p.proxy}|{p.username or ''}|{p.password or ''}"
        if key in unique_proxies:
            # If an exact same proxy+credentials exists, prefer the one with credentials
            if p.has_credentials():
                unique_proxies[key] = p
        else:
            unique_proxies[key] = p

    # Get a list of unique Proxy objects (distinct by proxy+credentials)
    unique_proxy_list = list(unique_proxies.values())

    return unique_proxy_list
