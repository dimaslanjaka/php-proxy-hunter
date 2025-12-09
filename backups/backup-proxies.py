import sys
import json
import argparse
from pathlib import Path
from typing import Any, Dict, List

ROOT = str(Path(__file__).parent.parent)
sys.path.insert(0, ROOT)

from src.shared import init_db
from src.func import get_relative_path


def dict_from_sqlite_row(row) -> Dict[str, Any]:
    try:
        return dict(row)
    except Exception:
        # fallback: map numeric indexes to string keys
        return {str(k): row[k] for k in range(len(row))}


def export_in_chunks(proxy_db, out_dir: str, chunk_size: int = 10000):
    db = proxy_db.get_db()
    out_path = Path(out_dir)
    out_path.mkdir(parents=True, exist_ok=True)

    chunk_index = 1
    last_id = 0

    # Try id-based pagination first (more efficient / stable than OFFSET loops)
    use_id_pagination = True

    while True:
        rows: List[Dict[str, Any]] = []

        if use_id_pagination:
            try:
                if (
                    hasattr(db, "cursor")
                    and getattr(db, "cursor") is not None
                    and db.__class__.__name__ == "MySQLHelper"
                ):
                    # MySQL: use server cursor via existing cursor
                    sql = "SELECT * FROM proxies WHERE id > %s ORDER BY id ASC LIMIT %s"
                    db.cursor.execute(sql, (last_id, chunk_size))
                    rows = db.cursor.fetchall() or []
                else:
                    # SQLiteHelper: get a fresh cursor
                    cur = db.conn.cursor()
                    try:
                        sql = (
                            "SELECT * FROM proxies WHERE id > ? ORDER BY id ASC LIMIT ?"
                        )
                        cur.execute(sql, (last_id, chunk_size))
                        fetched = cur.fetchall()
                        if fetched:
                            # sqlite3.Row -> dict
                            rows = [dict(r) for r in fetched]
                        else:
                            rows = []
                    finally:
                        cur.close()
            except Exception:
                # id-based pagination failed (maybe no `id` column). Fall back to OFFSET strategy.
                use_id_pagination = False

        if not use_id_pagination:
            # fallback to offset pagination
            offset = (chunk_index - 1) * chunk_size
            try:
                if hasattr(db, "cursor") and db.__class__.__name__ == "MySQLHelper":
                    sql = "SELECT * FROM proxies LIMIT %s OFFSET %s"
                    db.cursor.execute(sql, (chunk_size, offset))
                    rows = db.cursor.fetchall() or []
                else:
                    cur = db.conn.cursor()
                    try:
                        sql = "SELECT * FROM proxies LIMIT ? OFFSET ?"
                        cur.execute(sql, (chunk_size, offset))
                        fetched = cur.fetchall()
                        rows = [dict(r) for r in fetched] if fetched else []
                    finally:
                        cur.close()
            except Exception as e:
                print(f"Error fetching rows with OFFSET pagination: {e}")
                break

        if not rows:
            # no more rows
            print("No more rows to export.")
            break

        # normalize rows to plain dicts (MySQL returns dicts already)
        normalized: List[Dict[str, Any]] = []
        for r in rows:
            if isinstance(r, dict):
                normalized.append(r)
            else:
                normalized.append(dict_from_sqlite_row(r))

        # write chunk file as JSON array
        filename = out_path / f"proxies_chunk_{chunk_index:04d}.json"
        with open(filename, "w", encoding="utf-8") as f:
            json.dump(normalized, f, ensure_ascii=False)

        print(f"Wrote {len(normalized)} rows to {filename}")

        # advance
        if use_id_pagination:
            try:
                ids = [
                    int(item.get("id", 0))
                    for item in normalized
                    if item.get("id") is not None
                ]
                if ids:
                    last_id = max(ids)
                else:
                    # can't determine ids -> fall back to OFFSET
                    use_id_pagination = False
            except Exception:
                use_id_pagination = False

        chunk_index += 1


def main():
    parser = argparse.ArgumentParser(
        description="Export proxies to chunked files to avoid high memory usage"
    )
    parser.add_argument(
        "--db-type", default="mysql", help="Database type to initialize (mysql|sqlite)"
    )
    parser.add_argument(
        "--chunk-size", type=int, default=10000, help="Number of rows per chunk file"
    )
    parser.add_argument(
        "--out-dir",
        default=get_relative_path("backups/proxies"),
        help="Output directory for chunk files",
    )
    # format option removed: output is always JSON arrays (one file per chunk)

    args = parser.parse_args()

    proxy_db = init_db(db_type=args.db_type)
    if proxy_db.db is None:
        print("Database not initialized")
        return

    export_in_chunks(proxy_db, args.out_dir, chunk_size=args.chunk_size)


if __name__ == "__main__":
    main()
