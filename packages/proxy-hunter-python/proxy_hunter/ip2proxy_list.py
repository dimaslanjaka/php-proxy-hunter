from typing import List


def generate_ip_port_pairs(ip: str, start_port: int = 80, max_port: int = 65535):
    ip_parts = ip.split(".")
    if len(ip_parts) != 4:
        raise ValueError("Invalid IP address format")

    base_ip_parts = list(map(int, ip_parts))
    pairs: List[str] = []

    for port in range(start_port, max_port + 1):
        ip = f"{base_ip_parts[0]}.{base_ip_parts[1]}.{base_ip_parts[2]}.{base_ip_parts[3]}"
        pairs.append((ip, port))

    return pairs


if __name__ == "__main__":
    base_ip = "192.168.0.1"
    start_port = 80  # Starting port

    ip_port_pairs = generate_ip_port_pairs(base_ip, start_port)

    for ip, port in ip_port_pairs:
        print(f"{ip}:{port}")