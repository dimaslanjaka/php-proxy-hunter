"""Blacklist helpers for loading and checking IP entries.

This module ports the behavior from `src/blacklist/blacklist.php`:
- `get_blacklist()` reads a blacklist file and returns unique IPs.
- IPv4 CIDR entries are expanded with a safety cap.
- `is_blacklist()` accepts IP, IP:PORT, [IPv6]:PORT, or raw IPv6.
"""

from __future__ import annotations

from ipaddress import IPv4Address, ip_address, ip_network
from proxy_hunter import extract_proxies
from pathlib import Path
import re

MAX_CIDR_EXPANSION = 65536
_IPV4_IN_TEXT_RE = re.compile(r"(\d{1,3}(?:\.\d{1,3}){3})")


def _default_blacklist_path() -> Path:
    """Return the default blacklist file path used by this project."""
    return Path(__file__).resolve().parents[2] / "data" / "blacklist.conf"


def get_blacklist(blacklist_conf: str | None = None) -> list[str]:
    """Load blacklist IPs from file.

    Args:
            blacklist_conf: Optional path to a blacklist config file.

    Returns:
            A list of unique IP strings. Returns an empty list if the file
            is missing, unreadable, or contains no valid entries.
    """
    path = Path(blacklist_conf) if blacklist_conf else _default_blacklist_path()
    if not path.is_file():
        return []

    results: dict[str, bool] = {}

    try:
        with path.open("r", encoding="utf-8", errors="ignore") as handle:
            for raw_line in handle:
                line = raw_line.strip()
                if not line or line.startswith("#"):
                    continue

                if "/" in line:
                    # Expand only IPv4 CIDR entries and skip very large ranges.
                    try:
                        network = ip_network(line, strict=False)
                    except ValueError:
                        continue

                    if network.version != 4:
                        continue

                    if network.num_addresses > MAX_CIDR_EXPANSION:
                        continue

                    for ip in network:
                        results[str(ip)] = True
                else:
                    try:
                        parsed = ip_address(line)
                    except ValueError:
                        continue
                    results[str(parsed)] = True
    except OSError:
        return []

    return list(results.keys())


def is_blacklist(proxy: str, blacklist_conf: str | None = None) -> bool:
    """Check whether an IP extracted from proxy text is blacklisted.

    Accepted proxy formats:
    - ``IP``
    - ``IP:PORT``
    - ``[IPv6]:PORT``
    - raw ``IPv6``
    """
    if not isinstance(proxy, str) or not proxy.strip():
        return False

    value = proxy.strip()
    ip: str | None = None

    # IPv6 in brackets: [::1]:8080 or [::1]
    bracket_match = re.match(r"^\[([^\]]+)\](?::\d+)?$", value)
    if bracket_match:
        ip = bracket_match.group(1)
    else:
        # Whole string as IP first.
        try:
            parsed = ip_address(value)
            ip = str(parsed)
        except ValueError:
            pass

        # Candidate from IP:PORT where the left side can be IPv4 or IPv6.
        if ip is None:
            port_match = re.match(r"^(.+):\d+$", value)
            if port_match:
                candidate = port_match.group(1)
                try:
                    parsed_candidate = ip_address(candidate)
                    ip = str(parsed_candidate)
                except ValueError:
                    pass

        # Fallback: first IPv4-like occurrence in text.
        if ip is None:
            fallback = _IPV4_IN_TEXT_RE.search(value)
            if fallback:
                ip = fallback.group(1)

    if ip is None:
        return False

    blacklist = get_blacklist(blacklist_conf)
    if not blacklist:
        return False

    return ip in blacklist


if __name__ == "__main__":
    proxies = "206.123.156.232:8080"
    ips = extract_proxies(proxies)
    for data in ips:
        ip = data.proxy.split(":")[0] if ":" in data.proxy else data.proxy
        print(f"Checking {ip} against blacklist...")
        if is_blacklist(ip):
            print(f"{ip} is blacklisted.")
        else:
            print(f"{ip} is not blacklisted.")
