import argparse
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, List, Sequence

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

from src.func import get_relative_path
from src.MySQLHelper import MySQLHelper
from src.ProxyDB import ProxyDB
from src.shared import init_db
from src.func_platform import is_debug


def row_to_dict(row) -> Dict[str, Any]:
    if isinstance(row, dict):
        return row
    try:
        return dict(row)
    except Exception:
        return {str(i): row[i] for i in range(len(row))}


def fetch_rows(db, sql: str, params: tuple) -> Sequence[Any]:
    if isinstance(db, MySQLHelper) and getattr(db, "cursor", None):
        db.cursor.execute(sql, params)
        return db.cursor.fetchall() or []
    else:
        cur = db.conn.cursor()
        try:
            cur.execute(sql, params)
            return cur.fetchall() or []
        finally:
            cur.close()


def export_in_chunks(proxy_db: ProxyDB, out_dir: str, chunk_size: int = 10000):
    db = proxy_db.get_db()
    out_path = Path(out_dir)
    out_path.mkdir(parents=True, exist_ok=True)

    chunk_index = 1
    last_id = 0
    use_id_pagination = True

    while True:
        try:
            if use_id_pagination:
                sql = "SELECT * FROM proxies WHERE id > %s ORDER BY id ASC LIMIT %s"
                params = (last_id, chunk_size)
                if not isinstance(db, MySQLHelper):
                    sql = sql.replace("%s", "?")
                rows = fetch_rows(db, sql, params)
            else:
                offset = (chunk_index - 1) * chunk_size
                sql = "SELECT * FROM proxies LIMIT %s OFFSET %s"
                params = (chunk_size, offset)
                if not isinstance(db, MySQLHelper):
                    sql = sql.replace("%s", "?")
                rows = fetch_rows(db, sql, params)
        except Exception:
            if use_id_pagination:
                use_id_pagination = False
                continue
            raise

        if not rows:
            print("No more rows to export.")
            break

        normalized = [row_to_dict(r) for r in rows]

        filename = out_path / f"proxies_chunk_{chunk_index:04d}.json"
        with open(filename, "w", encoding="utf-8") as f:
            json.dump(normalized, f, ensure_ascii=False)

        print(f"Wrote {len(normalized)} rows to {filename}")

        if use_id_pagination:
            try:
                last_id = int(normalized[-1]["id"])
            except Exception:
                use_id_pagination = False

        chunk_index += 1


def main():
    parser = argparse.ArgumentParser(description="Export proxies to chunked JSON files")
    parser.add_argument("--db-type", default="mysql", help="mysql|sqlite")
    parser.add_argument("--chunk-size", type=int, default=10000)
    parser.add_argument(
        "--out-dir",
        default=get_relative_path("backups/proxies"),
    )

    args = parser.parse_args()

    proxy_db = init_db(db_type=args.db_type, production=not is_debug())
    if not proxy_db.db:
        print("Database not initialized")
        return

    export_in_chunks(proxy_db, args.out_dir, chunk_size=args.chunk_size)


if __name__ == "__main__":
    main()
