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

    # Regular expression pattern to match IP:PORT pairs along with optional username and password
    pattern = r"(?:[a-zA-Z0-9!$%&*()_+=.-]+:[a-zA-Z0-9!$%&*()_+=.-]+@(?:\d{1,3}(?:\.\d{1,3}){3}:\d{2,5}|[\w.-]+:\d{2,5})|(?:\d{1,3}(?:\.\d{1,3}){3}:\d{2,5}|[\w.-]+:\d{2,5})@[a-zA-Z0-9!$%&*()_+=.-]+:[a-zA-Z0-9!$%&*()_+=.-]+|\d{1,3}(?:\.\d{1,3}){3}:\d{2,5}|[\w.-]+:\d{2,5})"

    # Perform the matching IP:PORT
    matches1 = re.findall(pattern, string, re.MULTILINE)

    # Perform the matching IP PORT (whitespaces)
    re_whitespace = r"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+((?!0)\d{2,5})"
    matches2 = re.findall(re_whitespace, string)
    matched_whitespaces = bool(matches2)
    # Normalize whitespace matches to "ip:port" strings
    matches2 = [f"{ip}:{port}" for ip, port in matches2]

    # Perform the matching IP PORT (json) to match "ip":"x.x.x.x","port":"xxxxx"
    pattern_json = r'"ip"\s*:\s*"((?!0)\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})"\s*,\s*"port"\s*:\s*"((?!0)\d{2,5})"'
    matches3 = re.findall(pattern_json, string)
    matched_json = bool(matches3)
    # Normalize json matches to "ip:port" strings
    matches3 = [f"{ip}:{port}" for ip, port in matches3]

    # Merge matches (all items are strings formatted as "ip:port" or proxy forms)
    matches = matches1 + matches2 + matches3

    for match in matches:

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
                    # Try to extract username/password from surrounding JSON if present
                    user_m = re.search(r'"user"\s*:\s*"([^"]+)"', string)
                    pass_m = re.search(r'"pass"\s*:\s*"([^"]+)"', string)
                    if user_m and pass_m:
                        username = user_m.group(1)
                        password = pass_m.group(1)
                        results.append(
                            Proxy(proxy=proxy, username=username, password=password)
                        )
                    else:
                        results.append(Proxy(proxy))
            continue

        if "@" in match:
            parts = match.split("@", 1)
            left = parts[0]
            right = parts[1]
            # print("has username and password")
            # Case 1: ip:port@user:pass
            if is_valid_proxy(left):
                ip_port = left
                username, password = (right.split(":") + [None, None])[:2]
                result = Proxy(proxy=ip_port, username=username, password=password)
                results.append(result)
            # Case 2: user:pass@ip:port
            elif is_valid_proxy(right):
                ip_port = right
                username, password = (left.split(":") + [None, None])[:2]
                result = Proxy(proxy=ip_port, username=username, password=password)
                results.append(result)
            # Case 3: hostname:port (domain) with credentials, e.g., user:pass@dc.example.io:8000
            elif re.match(r"^[\w\.-]+\:\d{2,5}$", right):
                ip_port = right
                username, password = (left.split(":") + [None, None])[:2]
                result = Proxy(proxy=ip_port, username=username, password=password)
                results.append(result)
            else:
                # Fallback: treat whole match as proxy string
                result = Proxy(match)
                results.append(result)
        else:
            result = Proxy(match)
            results.append(result)

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
