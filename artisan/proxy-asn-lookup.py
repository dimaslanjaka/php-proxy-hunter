import os
import sys
import argparse
from typing import List, Tuple

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.ASNLookup import ASNLookup
from proxy_hunter import extract_proxies
from src.shared import init_db


def main():
    parser = argparse.ArgumentParser(
        description="Lookup ASN and classification for IPs or a proxy string"
    )
    parser.add_argument("--proxy", help="Proxy string to parse into IP(s)")
    args = parser.parse_args()
    db = init_db("mysql", False)
    asn_lookup = ASNLookup()

    def _process_ips(datas: List[Tuple[str, str]]):
        for data in datas:
            proxy_str, ip = data
            info = asn_lookup.lookup(ip)
            classification = asn_lookup.classify(ip)
            db.update_data(
                proxy_str,
                {
                    "classification": classification,
                },
            )
            print(f"IP: {ip}")
            print(f"ASN Info: {info}")
            print(f"Classification: {classification}")
            print("-----")

    proxyData: List[Tuple[str, str]] = []
    try:
        if args.proxy:
            proxies = extract_proxies(args.proxy)
            if not proxies:
                print("No valid proxy found in the provided string.")
                return
            for proxy in proxies:
                ip = proxy.proxy.split(":")[0]
                proxyData.append((proxy.proxy, ip))
        else:
            # Default test data
            proxyData = [("115.114.77.133:9090", "115.114.77.133")]
        _process_ips(proxyData)
    finally:
        asn_lookup.close()


if __name__ == "__main__":
    main()
