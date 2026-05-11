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
        # Extract a clean host:port from noisy matched strings. Many logs
        # contain prefixed/trailing characters like 'XSDn209.1.2.3:80' or
        # "1.1.1.1:80n". Try to find the first IPv4/IPv6 + port pair and
        # preserve credentials from the original match.
        proxy_val = str(getattr(p, "proxy", "") or "").strip()
        # Pattern matches bracketed IPv6 or IPv4 followed by a port (allow 1-5 digits, including leading zeros)
        inner_pattern = (
            r"(\[[0-9a-fA-F:]+\]|(?:\d{1,3}(?:\.\d{1,3}){3}))[:\s]*(\d{1,5})"
        )
        m = re.search(inner_pattern, proxy_val)
        if m:
            host = m.group(1)
            port = m.group(2)
            # Normalize port by removing leading zeros
            port = str(int(port)) if port and port != "0" else port
            # Normalize IP if it's IPv4 (not bracketed IPv6)
            if not host.startswith("["):
                try:
                    # Remove leading zeros from IPv4 octets
                    octets = host.split(".")
                    host = ".".join(str(int(octet)) for octet in octets)
                except (ValueError, AttributeError):
                    pass
            clean_proxy = f"{host}:{port}"
            candidate = Proxy(
                proxy=clean_proxy, username=p.username, password=p.password
            )
            if is_valid_proxy(candidate.proxy):
                results.append(candidate)
            continue

        # Fallbacks:
        # 1) If the original proxy_val already looks like a valid proxy (hostname:port), keep it.
        if is_valid_proxy(proxy_val):
            results.append(
                Proxy(proxy=proxy_val, username=p.username, password=p.password)
            )
            continue

        # 2) Otherwise try simple leading 'n' sanitization (e.g. 'n1.2.3.4')
        if (
            proxy_val.startswith("n")
            and len(proxy_val) > 1
            and (proxy_val[1].isdigit() or proxy_val[1] == "[")
        ):
            proxy_val = proxy_val[1:]
            sanitized = Proxy(proxy=proxy_val, username=p.username, password=p.password)
            if is_valid_proxy(sanitized.proxy):
                results.append(sanitized)

    # Perform the matching IP PORT (whitespaces) - support bracketed IPv6
    re_whitespace = r"(\[[0-9a-fA-F:]+\]|(?:\d{1,3}(?:\.\d{1,3}){3}))\s+((?!0)\d{2,5})"
    matches2 = re.findall(re_whitespace, string)

    # Perform the matching IP PORT (json) to match "ip":"x.x.x.x" or IPv6 and "port":"xxxxx"
    pattern_json = r'"ip"\s*:\s*"([^\"]+)"\s*,\s*"port"\s*:\s*"((?!0)\d{2,5})"'
    matches3 = re.findall(pattern_json, string)

    # Process whitespace matches (tuples of (ip, port))
    for ip, port in matches2:
        # Strip leading 'n' if present in logs (e.g. 'n1.2.3.4' or 'n[2001:db8::1]')
        if (
            ip
            and ip.startswith("n")
            and len(ip) > 1
            and (ip[1].isdigit() or ip[1] == "[")
        ):
            ip = ip[1:]
        if is_valid_ip(ip):
            proxy = f"{ip}:{port}"
            if is_valid_proxy(proxy):
                results.append(Proxy(proxy))

    # Process JSON matches (tuples of (ip, port))
    for ip, port in matches3:
        # Strip leading 'n' if present in logs
        if (
            ip
            and ip.startswith("n")
            and len(ip) > 1
            and (ip[1].isdigit() or ip[1] == "[")
        ):
            ip = ip[1:]
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

    # (regex_match already processed above)

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
