import os
import sys
from typing import List

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.shared import init_db
from src.blacklist.blacklist import get_blacklist

blacklist_conf = os.path.join(PROJECT_ROOT, "data/blacklist.conf")

blacklists = get_blacklist(blacklist_conf)

db = init_db()
db_helper = db.get_db()
db_driver = db.driver  # sqlite or mysql

blacklisted_proxies: List[str] = []
deleted_rows = 0

if blacklists:
    db_helper.execute_query("DROP TEMPORARY TABLE IF EXISTS tmp_blacklist_ips")
    db_helper.execute_query(
        "CREATE TEMPORARY TABLE tmp_blacklist_ips (ip VARCHAR(45) PRIMARY KEY)"
    )

    chunk_size = 1000
    placeholder = "%s" if db_driver == "mysql" else "?"
    insert_prefix = "INSERT IGNORE" if db_driver == "mysql" else "INSERT OR IGNORE"

    for i in range(0, len(blacklists), chunk_size):
        chunk = blacklists[i : i + chunk_size]
        placeholders = ", ".join([f"({placeholder})"] * len(chunk))
        db_helper.execute_query(
            f"{insert_prefix} INTO tmp_blacklist_ips (ip) VALUES {placeholders}",
            chunk,
        )

    if db_driver == "mysql":
        sql = (
            "SELECT DISTINCT p.proxy "
            "FROM proxies p "
            "JOIN tmp_blacklist_ips b "
            "ON b.ip = REGEXP_SUBSTR(p.proxy, '[0-9]{1,3}(\\\\.[0-9]{1,3}){3}')"
        )
        try:
            rows = db_helper.execute_query_fetch(sql)
        except Exception:
            fallback_sql = (
                "SELECT DISTINCT p.proxy "
                "FROM proxies p "
                "JOIN tmp_blacklist_ips b "
                "ON b.ip = CASE "
                "WHEN LOCATE('@', p.proxy) > 0 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(p.proxy, '@', -1), ':', 1) "
                "WHEN LOCATE('://', p.proxy) > 0 THEN SUBSTRING_INDEX(SUBSTRING_INDEX(p.proxy, '://', -1), ':', 1) "
                "ELSE SUBSTRING_INDEX(p.proxy, ':', 1) END"
            )
            rows = db_helper.execute_query_fetch(fallback_sql)
    else:
        sqlite_sql = (
            "SELECT DISTINCT p.proxy "
            "FROM proxies p "
            "JOIN tmp_blacklist_ips b "
            "ON b.ip = CASE "
            "WHEN instr(p.proxy, '@') > 0 THEN "
            "  CASE WHEN instr(substr(p.proxy, instr(p.proxy, '@') + 1), ':') > 0 "
            "       THEN substr(substr(p.proxy, instr(p.proxy, '@') + 1), 1, instr(substr(p.proxy, instr(p.proxy, '@') + 1), ':') - 1) "
            "       ELSE substr(p.proxy, instr(p.proxy, '@') + 1) END "
            "WHEN instr(p.proxy, '://') > 0 THEN "
            "  CASE WHEN instr(substr(p.proxy, instr(p.proxy, '://') + 3), ':') > 0 "
            "       THEN substr(substr(p.proxy, instr(p.proxy, '://') + 3), 1, instr(substr(p.proxy, instr(p.proxy, '://') + 3), ':') - 1) "
            "       ELSE substr(p.proxy, instr(p.proxy, '://') + 3) END "
            "ELSE CASE WHEN instr(p.proxy, ':') > 0 "
            "          THEN substr(p.proxy, 1, instr(p.proxy, ':') - 1) "
            "          ELSE p.proxy END "
            "END"
        )
        rows = db_helper.execute_query_fetch(sqlite_sql)

    if isinstance(rows, list):
        blacklisted_proxies = [
            str(row.get("proxy", "")).strip()
            for row in rows
            if isinstance(row, dict) and row.get("proxy")
        ]

        if blacklisted_proxies:
            for i in range(0, len(blacklisted_proxies), chunk_size):
                delete_chunk = blacklisted_proxies[i : i + chunk_size]
                delete_placeholders = ", ".join([placeholder] * len(delete_chunk))
                deleted = db_helper.execute_query_fetch(
                    f"DELETE FROM proxies WHERE proxy IN ({delete_placeholders})",
                    delete_chunk,
                )
                if isinstance(deleted, int) and deleted > 0:
                    deleted_rows += deleted

blacklisted_ips = sorted(
    {item.split(":", 1)[0].strip("[]") for item in blacklisted_proxies}
)

print(f"blacklisted proxies found: {len(blacklisted_proxies)}")
print(f"blacklisted proxies deleted: {deleted_rows}")
