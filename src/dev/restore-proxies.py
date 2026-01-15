import argparse
import json
import sys, os
from pathlib import Path
from typing import Any, Dict, List

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

from src.func import get_relative_path
from src.ProxyDB import ProxyDB


def load_json(path: Path) -> List[Dict[str, Any]]:
    try:
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)
            if isinstance(data, list):
                return data
            # sometimes single object stored
            if isinstance(data, dict):
                return [data]
    except Exception as e:
        print(f"Failed to load {path}: {e}")
    return []


def restore_from_chunks(
    proxy_db: ProxyDB,
    in_dir: str,
    dry_run: bool = False,
    limit: int = 0,
    skip_errors: bool = False,
    overwrite: bool = True,
) -> None:
    path = Path(in_dir)
    if not path.exists():
        print(f"Input directory does not exist: {path}")
        return

    files = sorted(
        p
        for p in path.iterdir()
        if p.is_file()
        and p.name.startswith("proxies_chunk_")
        and p.suffix in (".json",)
    )
    total_files = len(files)
    print(f"Found {total_files} chunk files in {path}")

    processed = 0
    restored = 0

    for idx, f in enumerate(files, start=1):
        items = load_json(f)
        print(f"Processing {f} ({len(items)} items)")
        for item in items:
            if limit and restored >= limit:
                print("Reached limit, stopping")
                return
            if not isinstance(item, dict):
                continue
            proxy = item.get("proxy")
            if not proxy:
                processed += 1
                continue
            proxy = str(proxy).strip()

            # prepare data to update (exclude id/primary key)
            data = {k: v for k, v in item.items() if k not in ("id", "rowid", "proxy")}

            # if overwrite is false, do not write NULL values from backup
            if not overwrite:
                data = {k: v for k, v in data.items() if v is not None}

            try:
                # Determine existence only when needed
                exists = None
                if not overwrite:
                    try:
                        exists = proxy_db.select(proxy)
                    except Exception:
                        exists = None

                # Dry-run: describe action
                if dry_run:
                    if not overwrite and exists:
                        action = "skip"
                        print(
                            f"\r{processed+1} [{idx}/{total_files}] {proxy} -> {action}",
                            end="",
                            flush=True,
                        )
                    else:
                        action = "update"
                        print(
                            f"\r{processed+1} [{idx}/{total_files}] {proxy} -> {action}",
                            end="",
                            flush=True,
                        )
                        restored += 1
                else:
                    # Actual run
                    if not overwrite and exists:
                        action = "skip"
                    else:
                        action = "update"
                        proxy_db.update_data(proxy, data)
                        restored += 1
                    print(
                        f"\r{processed+1} [{idx}/{total_files}] {proxy} -> {action}            ",
                        end="",
                        flush=True,
                    )
            except Exception as e:
                print(f"Error restoring {proxy}: {e}")
                if not skip_errors:
                    raise
            processed += 1
        # ensure newline after inline output for this chunk
        print()
    print(f"Processed {processed} records, restored {restored}")


def main():
    parser = argparse.ArgumentParser(
        description="Restore proxies from chunked backup files into DB"
    )
    parser.add_argument(
        "--db-type", default="mysql", help="Database type to initialize (mysql|sqlite)"
    )
    parser.add_argument(
        "--in-dir",
        default=get_relative_path("backups/proxies"),
        help="Input directory containing chunk files",
    )
    parser.add_argument(
        "--dry-run", action="store_true", help="Do not write to DB; only show actions"
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=0,
        help="Optional limit on number of restored proxies (0 = no limit)",
    )
    parser.add_argument(
        "--skip-errors", action="store_true", help="Continue on individual item errors"
    )
    parser.add_argument(
        "--overwrite",
        default="true",
        help="Whether to overwrite existing DB values with backup values (true|false). Default: true",
    )
    parser.add_argument(
        "--db-target",
        choices=("local", "production"),
        help="Choose database target: local or production (prompts if not provided)",
    )
    # verbose logging is always enabled

    args = parser.parse_args()

    local_dbhost = os.getenv("MYSQL_HOST", "localhost")
    production_dbhost = os.getenv("MYSQL_HOST_PRODUCTION")

    db_name_local = "php_proxy_hunter_test"
    db_name_production = os.getenv("MYSQL_DBNAME", "php_proxy_hunter")

    db_user_production = os.getenv("MYSQL_USER_PRODUCTION")
    db_user_local = os.getenv("MYSQL_USER", "root")

    db_pass_production = os.getenv("MYSQL_PASS_PRODUCTION")
    db_pass_local = os.getenv("MYSQL_PASS", "")

    # Determine target: CLI override > interactive prompt (if TTY) > default 'local'
    target = args.db_target
    if not target:
        try:
            if sys.stdin.isatty():
                choice = input(
                    "Select database target ('local' or 'production') [local]: "
                )
                target = choice.strip().lower() or "local"
            else:
                target = "local"
        except Exception:
            target = "local"

    if target not in ("local", "production"):
        print(f"Unknown target '{target}', defaulting to 'local'")
        target = "local"

    if target == "production":
        # Enforce production-only values; fail if required production vars are not set
        if production_dbhost is None or production_dbhost == "":
            print(
                "Error: MYSQL_HOST_PRODUCTION is not set; cannot use production target"
            )
            sys.exit(1)
        db_host = production_dbhost

        if db_user_production is None or db_user_production == "":
            print(
                "Error: MYSQL_USER_PRODUCTION is not set; cannot use production target"
            )
            sys.exit(1)
        db_user = db_user_production

        # Allow empty production password but it must be defined (not None)
        if db_pass_production is None:
            print(
                "Error: MYSQL_PASS_PRODUCTION is not set; cannot use production target"
            )
            sys.exit(1)
        db_pass = db_pass_production
        db_name = db_name_production
    else:
        db_host = local_dbhost
        db_user = db_user_local
        db_pass = db_pass_local
        db_name = db_name_local

    # Export chosen credentials so another functions picks them up from environment
    os.environ["MYSQL_HOST"] = db_host
    os.environ["MYSQL_USER"] = db_user
    os.environ["MYSQL_PASS"] = db_pass
    os.environ["MYSQL_DBNAME"] = db_name

    if args.db_type == "sqlite":
        db_file = get_relative_path("src/database.sqlite")
        proxy_db = ProxyDB(
            db_location=db_file,
            start=True,
            db_type="sqlite",
        )
    else:
        proxy_db = ProxyDB(
            None,
            start=True,
            db_type="mysql",
            mysql_host=db_host,
            mysql_dbname=db_name,
            mysql_user=db_user,
            mysql_password=db_pass,
        )

    if proxy_db.db is None:
        print("Database not initialized")
        return

    overwrite_flag = str(args.overwrite).lower() in ("1", "true", "yes", "y")

    restore_from_chunks(
        proxy_db,
        args.in_dir,
        dry_run=args.dry_run,
        limit=args.limit,
        skip_errors=args.skip_errors,
        overwrite=overwrite_flag,
    )


if __name__ == "__main__":
    main()
