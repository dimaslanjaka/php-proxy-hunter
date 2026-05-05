import os
import random
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import get_device_ip
from artisan.proxy_getter import (
    load_proxies_from_cli,
    load_proxies_from_file,
    to_proxy_rows,
)
from src.utils.parse_args import parse_args
from src.func import get_relative_path
from src.func_console import blue, cyan, green, magenta, orange, red, white, yellow
from src.geoPlugin import get_geo_ip
from src.ProxyDB import ProxyDB
from src.database.SQLiteMarker import SQLiteMarker
from src.utils.file.FileLockHelper import FileLockHelper

if __name__ == "__main__":
    args = parse_args(default_limit=10)

    # Lock file name can be overridden with --uid
    lock_name = args.uid if getattr(args, "uid", None) else os.path.basename(__file__)
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{lock_name}.lock"))
    if not locker.lock():
        print(red("Another instance is running. Exiting."))
        sys.exit(0)

    db = ProxyDB()
    # Initialize marker here so it's always a valid instance (not None)
    marker = SQLiteMarker(
        db_filename="geoip.sqlite",
        table_name="geoip_checked",
        key_column="proxy",
        base_dir="tmp/database",
    )
    processed_proxies: list[str] = []
    try:
        proxy_file = get_relative_path("proxies.txt")

        # Load proxies from CLI, file, or DB (respecting --limit)
        proxies: list[str] = []
        source_label = "db"

        cli_proxies = load_proxies_from_cli()
        if cli_proxies:
            # normalize various proxy item types (Proxy objects, dicts, tuples)
            rows = to_proxy_rows(cli_proxies)
            proxies = [str(r.get("proxy")) for r in rows if r.get("proxy")]
            # If CLI provided a file via --file, resolve and assign it to
            # `proxy_file` so later removal uses the same file path.
            if getattr(args, "proxy_file", None) and args.proxy_file:
                proxy_file = (
                    args.proxy_file
                    if os.path.exists(args.proxy_file)
                    else get_relative_path(args.proxy_file)
                )
                source_label = f"file://{proxy_file}"
            else:
                source_label = "cli"

        if not proxies:
            file_proxies = load_proxies_from_file(proxy_file)
            if file_proxies:
                rows = to_proxy_rows(file_proxies)
                proxies = [str(r.get("proxy")) for r in rows if r.get("proxy")]
                source_label = f"file://{proxy_file}"

        if not proxies:
            rows = db.get_working_proxies(limit=args.limit, randomize=True)
            proxies = [
                str(row.get("proxy") or "").strip() for row in rows if row.get("proxy")
            ]
            source_label = "db"

        # Deduplicate while preserving order and filter already-checked proxies
        proxy_by_key: dict[str, str] = {}
        ordered_keys: list[str] = []
        for p in proxies:
            key = str(p).strip()
            if not key or key in proxy_by_key:
                continue
            proxy_by_key[key] = p
            ordered_keys.append(key)

        pending_keys, already_checked = marker.filter_unseen(ordered_keys)
        proxies = [proxy_by_key[k] for k in pending_keys]
        print(
            f"[MARKER] pending={len(proxies)}, already_checked={already_checked}, total_unique={len(ordered_keys)}"
        )

        random.shuffle(proxies)
        device_ip = get_device_ip()
        print(f"Device IP: {magenta(device_ip)}")
        print(f"Proxy source: {blue(source_label)} ({white(len(proxies))} candidates)")

        processed = 0
        processed_proxies: list[str] = []
        for proxy in proxies:
            if processed >= (args.limit or 100):
                break

            if not proxy:
                continue

            print(f"Testing proxy: {cyan(proxy)}")
            geoIp = get_geo_ip(proxy)
            if not geoIp:
                print(
                    red(
                        f"Failed to retrieve geolocation for proxy {cyan(proxy)}. Skipping..."
                    )
                )
                continue
            if not geoIp.country_name and not geoIp.city:
                print(
                    yellow(
                        f"Geolocation data for proxy {cyan(proxy)} is incomplete. Skipping..."
                    )
                )
                continue

            print(geoIp.to_json(), "\n")
            print(
                f"Proxy {cyan(proxy)} is located in {orange(geoIp.city) if geoIp.city else 'Unknown City'}, {yellow(geoIp.country_name) if geoIp.country_name else 'Unknown Country'}. {green('OK')}"
            )
            try:
                db.update_data(
                    proxy, {"city": geoIp.city, "country": geoIp.country_name}
                )
                processed += 1
                processed_proxies.append(proxy)
            except Exception as e:
                print(red(f"Failed to update DB for proxy {proxy}: {e}"))

        # If proxies were sourced from the proxies file, remove successfully
        # processed proxies from that file so they are not reprocessed later.
        if source_label.startswith("file") and processed_proxies:
            try:
                if os.path.exists(proxy_file):
                    with open(proxy_file, "r", encoding="utf-8") as f:
                        lines = f.readlines()

                    processed_set = {p.strip() for p in processed_proxies}
                    removed_count = sum(1 for l in lines if l.strip() in processed_set)

                    # Keep lines that are not in processed_set and are not empty
                    new_lines = [
                        l.rstrip("\n")
                        for l in lines
                        if l.strip() and l.strip() not in processed_set
                    ]

                    with open(proxy_file, "w", encoding="utf-8") as f:
                        if new_lines:
                            f.write("\n".join(new_lines) + "\n")
                        else:
                            # clear file
                            f.truncate(0)

                    print(
                        f"Removed {green(removed_count)} processed proxies from {blue(proxy_file)}"
                    )
            except Exception as e:
                print(red(f"Failed to update proxy file {proxy_file}: {e}"))

    finally:
        try:
            # Mark successfully processed proxies so they are skipped next run
            for p in processed_proxies:
                try:
                    marker.mark(p)
                except Exception:
                    pass
            marker.close()
        except Exception:
            pass

        if locker:
            locker.unlock()
        db.close()
