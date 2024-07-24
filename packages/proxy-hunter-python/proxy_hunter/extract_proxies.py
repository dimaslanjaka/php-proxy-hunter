import re
from typing import List, Optional
from .Proxy import Proxy
from .utils import *


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

    results = []

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

    return results


if __name__ == "__main__":
    proxies = extract_proxies(
        """
162.223.116.54:80@u:s
"""
    )
    print(proxies)
