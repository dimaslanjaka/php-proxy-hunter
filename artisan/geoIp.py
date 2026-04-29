import os
import random
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import get_device_ip
from artisan.proxy_getter import (
    load_proxies_from_cli,
    load_proxies_from_file,
    parse_args,
)
from src.func import get_relative_path
from src.func_console import blue, cyan, green, magenta, orange, red, white, yellow
from src.geoPlugin import get_geo_ip
from src.ProxyDB import ProxyDB
from src.utils.file.FileLockHelper import FileLockHelper

if __name__ == "__main__":
    args = parse_args(default_limit=1)

    # Lock file name can be overridden with --uid
    lock_name = args.uid if getattr(args, "uid", None) else os.path.basename(__file__)
    locker = FileLockHelper(get_relative_path(f"tmp/locks/{lock_name}.lock"))
    if not locker.lock():
        print(red("Another instance is running. Exiting."))
        sys.exit(0)

    db = ProxyDB()
    try:
        proxy_file = get_relative_path("proxies.txt")

        # Load proxies from CLI, file, or DB (respecting --limit)
        proxies: list[str] = []
        source_label = "db"

        cli_proxies = load_proxies_from_cli()
        if cli_proxies:
            proxies = [f"{h}:{p}" for (h, p) in cli_proxies]
            source_label = "cli"

        if not proxies:
            file_proxies = load_proxies_from_file(proxy_file)
            if file_proxies:
                proxies = [f"{h}:{p}" for (h, p) in file_proxies]
                source_label = f"file ({proxy_file})"

        if not proxies:
            rows = db.get_working_proxies(limit=args.limit, randomize=True)
            proxies = [
                str(row.get("proxy") or "").strip() for row in rows if row.get("proxy")
            ]
            source_label = "db"

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
                f"Proxy {cyan(proxy)} is located in {orange(geoIp.city) if geoIp.city else 'Unknown City'}, {yellow(geoIp.country_name) if geoIp.country_name else 'Unknown Country'}. {green('✓')}"
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
        if locker:
            locker.unlock()
        db.close()
