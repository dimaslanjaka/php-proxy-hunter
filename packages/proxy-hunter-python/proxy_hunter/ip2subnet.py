import ipaddress


def get_subnet_mask(cidr_prefix: int) -> ipaddress.IPv4Address:
    """
    Converts a CIDR prefix length to a subnet mask.

    Args:
        cidr_prefix (int): The CIDR prefix length (e.g., 24 for /24).

    Returns:
        ipaddress.IPv4Address: The subnet mask in IPv4 address format.
    """
    # Create an IPv4Network object with the given prefix length
    network = ipaddress.IPv4Network(f"0.0.0.0/{cidr_prefix}", strict=False)
    # Return the subnet mask
    return network.netmask


def get_default_subnet_mask(ip: str) -> ipaddress.IPv4Address:
    """
    Assumes a default subnet mask based on the IP address class.

    Args:
        ip (str): The IP address in string format.

    Returns:
        ipaddress.IPv4Address: The assumed default subnet mask.
    """
    ip_obj = ipaddress.IPv4Address(ip)

    if ip_obj in ipaddress.IPv4Network("0.0.0.0/8", strict=False):
        return ipaddress.IPv4Address("255.0.0.0")  # Class A
    elif ip_obj in ipaddress.IPv4Network("128.0.0.0/16", strict=False):
        return ipaddress.IPv4Address("255.255.0.0")  # Class B
    else:
        return ipaddress.IPv4Address("255.255.255.0")  # Class C


if __name__ == "__main__":
    # Example usage
    cidr_prefix = 24
    subnet_mask = get_subnet_mask(cidr_prefix)
    print(f"Subnet Mask for CIDR prefix /{cidr_prefix}: {subnet_mask}")

    ip_address = "192.168.1.10"
    subnet_mask = get_default_subnet_mask(ip_address)
    print(f"Assumed Subnet Mask for {ip_address}: {subnet_mask}")
