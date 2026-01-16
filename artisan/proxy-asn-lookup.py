import os
import sys
import argparse

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.ASNLookup import ASNLookup
from proxy_hunter import extract_ips


def main():
    parser = argparse.ArgumentParser(
        description="Lookup ASN and classification for IPs or a proxy string"
    )
    parser.add_argument("--proxy", help="Proxy string to parse into IP(s)")
    args = parser.parse_args()

    asn_lookup = ASNLookup()

    def _process_ips(ips):
        for ip in ips:
            info = asn_lookup.lookup(ip)
            classification = asn_lookup.classify(ip)
            print(f"IP: {ip}")
            print(f"ASN Info: {info}")
            print(f"Classification: {classification}")
            print("-----")

    try:
        if args.proxy:
            ips = extract_ips(args.proxy)
            if not ips:
                print("No IPs extracted from --proxy value")
                return
        else:
            ips = ["8.8.8.8"]

        _process_ips(ips)
    finally:
        asn_lookup.close()


if __name__ == "__main__":
    main()
