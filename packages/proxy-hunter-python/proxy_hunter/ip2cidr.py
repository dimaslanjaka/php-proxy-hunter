import ipaddress


def calculate_cidr(ip: str, subnet_mask: str) -> str:
    """
    Calculate the CIDR notation for a given IP address and subnet mask.

    Args:
        ip (str): The IP address as a string (e.g., '192.168.1.10').
        subnet_mask (str): The subnet mask as a string (e.g., '255.255.255.0').

    Returns:
        str: The CIDR notation as a string (e.g., '192.168.1.0/24').

    Raises:
        ValueError: If the IP address or subnet mask is invalid.
    """
    try:
        # Create an IPv4 network object
        network = ipaddress.IPv4Network(f"{ip}/{subnet_mask}", strict=False)
        return str(network.with_prefixlen)
    except ValueError as e:
        raise ValueError(f"Invalid IP address or subnet mask: {e}")


if __name__ == "__main__":
    # Example usage
    ip = "192.168.1.10"
    subnet_mask = "255.255.255.0"

    try:
        cidr = calculate_cidr(ip, subnet_mask)
        print(f"CIDR Notation: {cidr}")
    except ValueError as e:
        print(e)
