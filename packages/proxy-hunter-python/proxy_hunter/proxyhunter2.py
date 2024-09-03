from proxy_hunter.cidr2ips import list_ips_from_cidr
from proxy_hunter.ip2cidr import calculate_cidr
from proxy_hunter.ip2proxy_list import generate_ip_port_pairs
from proxy_hunter.ip2subnet import get_default_subnet_mask


if __name__ == "__main__":
    ip, port = "156.34.105.58:5678".split(":")
    subnet_mask = get_default_subnet_mask(ip)
    cidr = calculate_cidr(ip, subnet_mask)
    ips = list_ips_from_cidr(cidr)
    proxies = [generate_ip_port_pairs(ip) for ip in ips]
