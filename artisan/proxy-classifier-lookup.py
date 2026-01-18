import argparse
import os
import sys
from typing import List, Tuple

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from proxy_hunter import extract_proxies
from src.ASNLookup import ASNLookup
from src.func import get_relative_path
from src.shared import init_db, init_readonly_db
from src.utils.file.FileLockHelper import FileLockHelper
from src.func_platform import is_debug

current_filename = os.path.basename(__file__)
locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_filename}.lock"))
if not locker.lock():
    print("Another instance is running. Exiting.")
    sys.exit(0)


def main():
    parser = argparse.ArgumentParser(
        description="Lookup ASN and classification for IPs or a proxy string"
    )
    parser.add_argument("--proxy", help="Proxy string to parse into IP(s)")
    parser.add_argument(
        "--production",
        action="store_true",
        help="Use readonly production database (init_readonly_db)",
    )
    args = parser.parse_args()
    db = init_readonly_db() if args.production else init_db("mysql")
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
            print(f"Proxy: {proxy_str} ({ip})")
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
        elif db.db:
            limit = 1000
            # Select proxies without classification to update
            proxies_db = db.db.select(
                "proxies",
                columns="proxy",
                where="classification = %s OR classification IS NULL AND status = %s",
                params=("", "active"),
                limit=limit,
            )
            if not proxies_db:
                # Select proxies with unknown classification to update
                proxies_db = db.db.select(
                    "proxies",
                    columns="proxy",
                    where="classification IS NULL AND status = %s",
                    params=("active",),
                    limit=limit,
                )
            for row in proxies_db:
                proxy_str = row["proxy"]
                ip = proxy_str.split(":")[0]
                proxyData.append((proxy_str, ip))
        else:
            # Default test data
            proxyData = [("115.114.77.133:9090", "115.114.77.133")]

        _process_ips(proxyData)
    finally:
        asn_lookup.close()
        db.close()


if __name__ == "__main__":
    main()
