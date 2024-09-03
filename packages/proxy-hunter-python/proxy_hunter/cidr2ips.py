import ipaddress
from typing import List


def list_ips_from_cidr(cidr: str) -> List[str]:
    """
    Generates a list of IP addresses within a given CIDR block.

    Args:
        cidr (str): The CIDR block (e.g., '192.168.1.0/28').

    Returns:
        List[str]: A list of IP addresses as strings.
    """
    network = ipaddress.ip_network(cidr)
    return [str(ip) for ip in network.hosts()]


if __name__ == "__main__":
    cidr = "192.168.1.0/28"
    ips = list_ips_from_cidr(cidr)
    for ip in ips:
        print(ip)
