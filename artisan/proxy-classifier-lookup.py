import argparse
import os
import sys
from typing import List, Tuple

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from proxy_hunter import extract_proxies
from src.ASNLookup import ASNLookup
from src.ProxyDB import ProxyDB
from src.func import get_relative_path
from src.shared import init_db, init_readonly_db
from src.utils.file.FileLockHelper import FileLockHelper
from src.func_platform import is_debug

current_filename = os.path.basename(__file__)
locker = None


def main():
    global locker
    parser = argparse.ArgumentParser(
        description="Lookup ASN and classification for IPs or a proxy string"
    )
    parser.add_argument(
        "--uid",
        type=str,
        help="Override lock filename (unique id)",
    )
    parser.add_argument("--proxy", help="Proxy string to parse into IP(s)")
    parser.add_argument(
        "--production",
        action="store_true",
        help="Use production database (write-enabled; may perform destructive operations)",
    )
    parser.add_argument(
        "--readonly",
        action="store_true",
        help="Use readonly production database (init_readonly_db)",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=1000,
        help="Limit number of proxies to process when looking up classification",
    )
    args = parser.parse_args()
    # Apply optional UID override for the lock filename
    current_lock_name = (
        args.uid if getattr(args, "uid", None) else os.path.basename(__file__)
    )

    # Create and acquire file lock after CLI parsing to allow overrides
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{current_lock_name}.lock"))
    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)
    if args.readonly:
        db = init_readonly_db()
    elif args.production:
        db_name = os.getenv("MYSQL_DBNAME", "php_proxy_hunter")
        db_host = os.getenv(
            "MYSQL_HOST_PRODUCTION", os.getenv("MYSQL_HOST", "localhost")
        )
        db_user = os.getenv("MYSQL_USER_PRODUCTION", os.getenv("MYSQL_USER", "root"))
        db_pass = os.getenv("MYSQL_PASS_PRODUCTION", os.getenv("MYSQL_PASS", ""))
        db = ProxyDB(
            start=True,
            db_type="mysql",
            mysql_host=db_host,
            mysql_dbname=db_name,
            mysql_user=db_user,
            mysql_password=db_pass,
        )
    else:
        db = init_db("mysql")
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
            limit = args.limit
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
        if locker:
            try:
                locker.unlock()
            except Exception:
                pass


if __name__ == "__main__":
    main()
