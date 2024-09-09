from proxy_hunter.proxyhunter2 import gen_ports, iterate_gen_ports

if __name__ == "__main__":
    proxy = "156.34.105.58:5678"
    gen_ports(proxy)
    iterate_gen_ports(proxy)
