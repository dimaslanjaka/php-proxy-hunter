import json
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional, Union, cast

from proxy_hunter import (
    Proxy,
    extract_proxies,
    file_append_str,
    random_windows_ua,
    read_file,
)

from data.webgl import random_webgl_data
from src.func import get_nuitka_file, get_relative_path
from src.func_date import get_current_rfc3339_time
from src.geoPlugin import get_geo_ip
from src.SQLiteHelper import SQLiteHelper
from src.MySQLHelper import MySQLHelper


class ProxyDB:
    """
    ProxyDB manages proxy data storage and operations using an SQLite database.

    Attributes:
        db (SQLiteHelper): The SQLiteHelper instance for database operations. This attribute is available after `start_connection()` has been called at least once.

    Note:
        Ensure `start_connection()` is called before accessing or using `self.db` to establish the database connection.
    """

    db: Optional[Union[SQLiteHelper, MySQLHelper]] = None

    # noinspection PyMethodMayBeStatic
    def _find_mysql_schema_file(self) -> Optional[str]:
        """Return the first existing mysql schema path, or None when missing."""
        candidates = [
            get_relative_path("src/PhpProxyHunter/assets/mysql-schema.sql"),
            os.path.join(
                os.path.dirname(os.path.abspath(__file__)),
                "PhpProxyHunter",
                "assets",
                "mysql-schema.sql",
            ),
            os.path.join(
                os.path.dirname(os.path.abspath(__file__)),
                "assets",
                "mysql-schema.sql",
            ),
        ]

        for path in candidates:
            if os.path.exists(path):
                return path

        current_dir = os.path.dirname(os.path.abspath(__file__))
        for root, _dirs, files in os.walk(current_dir):
            if "mysql-schema.sql" in files:
                return os.path.join(root, "mysql-schema.sql")
        return None

    def __init__(
        self,
        db_location: Optional[Union[str, SQLiteHelper, MySQLHelper]] = None,
        start: bool = False,
        check_same_thread: bool = False,
        db_type: str = "sqlite",
        mysql_host: str = "localhost",
        mysql_dbname: str = "php_proxy_hunter",
        mysql_user: str = "root",
        mysql_password: str = "",
    ):
        """
        Initialize ProxyDB instance.

        Args:
            db_location (Optional[str]): The location of the SQLite database file. If None, uses default path.
            start (bool): If True, automatically starts the database connection.
        """
        self.check_same_thread = check_same_thread
        self.db_location = db_location
        self.db: Optional[Union[SQLiteHelper, MySQLHelper]] = None
        # keep parameter name `db_type` for backward compatibility
        # expose instance attribute as `driver` per new naming
        self.driver = db_type.lower() if db_type else "sqlite"
        self.mysql_host = mysql_host
        self.mysql_dbname = mysql_dbname
        self.mysql_user = mysql_user
        self.mysql_password = mysql_password
        if isinstance(db_location, (SQLiteHelper, MySQLHelper)):
            # accept helper instance directly
            self.db = db_location
            return
        if db_location is None:
            # only default to sqlite file when not using MySQL backend
            if self.driver != "mysql":
                self.db_location = get_relative_path("src/database.sqlite")
            else:
                self.db_location = None
        if start:
            self.start_connection()

    def start_connection(self):
        """Establishes a connection to the SQLite database and sets up initial configurations."""
        try:
            # Decide backend
            if self.driver == "mysql":
                # Initialize MySQL helper
                # db_location may be used to override dbname
                # If db_location is a path to sqlite, prefer mysql_dbname
                if (
                    self.db_location
                    and isinstance(self.db_location, str)
                    and not self.db_location.lower().endswith(".sqlite")
                ):
                    dbname = self.db_location
                else:
                    dbname = self.mysql_dbname
                self.db = MySQLHelper(
                    host=self.mysql_host,
                    user=self.mysql_user,
                    password=self.mysql_password,
                    database=dbname,
                )
                # load mysql schema if available
                try:
                    sql_file = self._find_mysql_schema_file()
                    if sql_file and os.path.exists(sql_file):
                        contents = str(read_file(sql_file))
                        if contents:
                            # MySQLHelper exposes execute_query but uses %s params; execute as raw
                            for stmt in contents.split(";"):
                                stmt = stmt.strip()
                                if stmt:
                                    self.db.execute_query(stmt)
                except Exception:
                    # ignore missing schema file
                    pass
            else:
                if not self.db_location:
                    self.db_location = get_relative_path("src/database.sqlite")
                # mypy/pylance: ensure db_path is a str when calling SQLiteHelper
                self.db = SQLiteHelper(
                    cast(str, self.db_location),
                    check_same_thread=self.check_same_thread,
                )
                # create table proxies when not exist
                db_create_file = get_nuitka_file("assets/database/create.sql")
                contents = str(read_file(db_create_file))
                commands = contents.split(";")
                if contents:
                    # Loop through each command
                    for command in commands:
                        # Strip any leading/trailing whitespace
                        command = command.strip()
                        # Ignore empty commands
                        if command:
                            self.db.execute_query(command)

            # SQLite-specific pragmas
            if self.driver != "mysql":
                wal_enabled = self.get_meta_value("wal_enabled")
                if not wal_enabled:
                    try:
                        self.db.execute_query("PRAGMA journal_mode = WAL")
                        self.db.execute_query("PRAGMA wal_autocheckpoint = 100")
                        self.set_meta_value("wal_enabled", "1")
                    except Exception:
                        pass

                auto_vacuum_enabled = self.get_meta_value("auto_vacuum_enabled")
                if not auto_vacuum_enabled:
                    try:
                        self.db.execute_query("PRAGMA auto_vacuum = FULL")
                        self.set_meta_value("auto_vacuum_enabled", "1")
                    except Exception:
                        pass

                self.run_daily_vacuum()
        except Exception as e:
            file_append_str(get_nuitka_file("error.txt"), str(e))
            print(e)

    def close(self):
        """Closes the database connection if open."""
        if self.db:
            try:
                self.db.close()
            except Exception as e:
                print(f"cannot close database: {e}")

    def get_db(self) -> Union[SQLiteHelper, MySQLHelper]:
        """
        Retrieves the SQLiteHelper database instance.

        Returns:
            Union[SQLiteHelper, MySQLHelper]: The database instance.
        """
        if not self.db:
            self.start_connection()
        assert self.db is not None
        return self.db

    def get_meta_value(self, key: str) -> Optional[str]:
        """
        Retrieves a meta value from the database.

        Args:
            key (str): The key for which to retrieve the value.

        Returns:
            Optional[str]: The meta value associated with the key, or None if not found.
        """
        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            result = self.get_db().select("meta", "value", "key = %s", (key,))
        else:
            result = self.get_db().select("meta", "value", "key = ?", (key,))
        return result[0]["value"] if result else None

    def set_meta_value(self, key: str, value: str) -> None:
        """
        Sets a meta value in the database.

        Args:
            key (str): The key to set.
            value (str): The value to set.
        """

        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            sql = "REPLACE INTO meta (key, value) VALUES (%s, %s)"
        else:
            sql = "REPLACE INTO meta (key, value) VALUES (?, ?)"
        self.get_db().execute_query(sql, (key, value))

    def run_daily_vacuum(self):
        last_vacuum_time: Optional[str] = self.get_meta_value("last_vacuum_time")
        current_time: int = int(time.time())
        one_day_in_seconds: int = 86400

        if not last_vacuum_time or (
            current_time - int(last_vacuum_time) > one_day_in_seconds
        ):
            self.get_db().execute_query("VACUUM")
            # https://stackoverflow.com/a/37865221/6404439
            self.get_db().execute_query(
                "PRAGMA wal_checkpoint(SQLITE_CHECKPOINT_TRUNCATE);"
            )
            self.set_meta_value("last_vacuum_time", str(current_time))

    def select(self, proxy: Optional[str]):
        if not proxy:
            return []
        # both helpers accept select(table, columns, where, params, rand, limit, offset) loosely
        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            return self.get_db().select("proxies", "*", "proxy = %s", [proxy.strip()])
        else:
            return self.get_db().select("proxies", "*", "proxy = ?", [proxy.strip()])

    def is_already_added(self, proxy: Optional[str]) -> bool:
        proxy = self.normalize_proxy(proxy)
        if not proxy:
            return False
        try:
            if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
                res = self.get_db().select(
                    "added_proxies", "count(*) as c", "proxy = %s", [proxy.strip()]
                )
            else:
                res = self.get_db().select(
                    "added_proxies", "count(*) as c", "proxy = ?", [proxy.strip()]
                )
            if res:
                # SQLite returns list of dicts
                row = res[0]
                if isinstance(row, dict):
                    return int(row.get("c", 0)) > 0
                # MySQL may return similar
                return bool(row)
        except Exception:
            try:
                # fallback to direct query
                self.get_db().execute_query(
                    "SELECT COUNT(*) FROM added_proxies WHERE proxy = %s",
                    [proxy.strip()],
                )
                return True
            except Exception:
                return False
        return False

    def mark_as_added(self, proxy: Optional[str]) -> None:
        proxy = self.normalize_proxy(proxy)
        if not proxy:
            return
        if self.is_already_added(proxy):
            return
        try:
            if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
                self.get_db().execute_query(
                    "INSERT INTO added_proxies (proxy) VALUES (%s)", [proxy.strip()]
                )
            else:
                self.get_db().execute_query(
                    "INSERT INTO added_proxies (proxy) VALUES (?)", [proxy.strip()]
                )
        except Exception:
            try:
                # fallback
                self.get_db().execute_query(
                    "INSERT INTO added_proxies (proxy) VALUES (%s)", [proxy.strip()]
                )
            except Exception:
                pass

    def get_random_function(self) -> str:
        """Return SQL RANDOM function name depending on backend."""
        return "RAND()" if isinstance(self.db, MySQLHelper) else "RANDOM()"

    def normalize_proxy(self, proxy: Optional[str]) -> str:
        """Normalize and validate a proxy string using extract_proxies()."""
        if proxy is None:
            return ""
        proxy = proxy.strip()

        # Remove URL scheme if present (e.g., "http://", "https://")
        if "://" in proxy:
            proxy = proxy.split("://", 1)[1]

        # Remove trailing colon or other non-alphanumeric suffix characters
        proxy = re.sub(r"[:;,\s]+$", "", proxy)

        # Find IP:PORT pattern anywhere in the string, allowing leading zeros
        pattern = r"(\d{1,3}(?:\.\d{1,3}){3}):(\d+)"
        match = re.search(pattern, proxy)
        if not match:
            return ""

        # Normalize both IP octets and port (remove leading zeros)
        ip, port = match.groups()
        try:
            # Normalize IP octets: convert to int to remove leading zeros, then back to string
            octets = [str(int(octet)) for octet in ip.split(".")]

            # Validate that all octets are in valid range (0-255)
            for octet in octets:
                if not 0 <= int(octet) <= 255:
                    return ""

            # Normalize port: convert to int to remove leading zeros, validate range
            port_num = int(port)
            if not 1 <= port_num <= 65535:
                return ""

            return f"{'.'.join(octets)}:{port_num}"
        except (ValueError, IndexError):
            return ""

    def get_all_proxies(
        self,
        limit: Optional[int] = None,
        randomize: Optional[bool] = None,
        page: Optional[int] = None,
        per_page: Optional[int] = None,
        status: Optional[str] = None,
        last_checked: Optional[str] = None,
    ) -> List[Dict[str, Union[str, None]]]:
        """Get all proxies with optional pagination, randomization, and filtering.

        Args:
            limit (Optional[int]): Maximum number of results (legacy single-argument limit).
            randomize (Optional[bool]): When True, order results randomly. When False, order
                by most recent. When None, randomize if limit is provided (backwards-compatible).
            page (Optional[int]): 1-based page number for pagination. Takes precedence over limit.
            per_page (Optional[int]): Number of items per page for pagination.
            status (Optional[str]): Filter by status column. Valid values: "dead", "active",
                "untested", "port-closed", "port-open". When None, no status filtering.
            last_checked (Optional[str]): Filter by last_check column (RFC3339 date string).
                When provided, only returns proxies that were checked on or before this date
                (i.e., last_check <= last_checked).

        Returns:
            List[Dict[str, Union[str, None]]]: List of proxy rows as dictionaries.

        Backwards-compatible: when only `limit` is provided it behaves like before
        (providing a positive limit implies randomization unless `randomize` is set).
        """
        # Pagination (page/perPage) takes precedence over legacy limit
        offset = None
        final_limit = limit
        if page is not None and per_page is not None:
            page = max(1, int(page))
            per_page = max(0, int(per_page))
            offset = (page - 1) * per_page
            final_limit = per_page

        # Build backend-specific query and params
        params: List[Union[str, int]] = []
        where_clause: str = "1=1"  # Always start with a true condition

        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            placeholder = "%s"
        else:
            placeholder = "?"

        # Status filtering
        if status:
            where_clause += f" AND status = {placeholder}"
            params.append(status)

        # Last checked filtering (last_check <= last_checked)
        if last_checked:
            where_clause += f" AND last_check <= {placeholder}"
            params.append(last_checked)

        # Determine ordering (after building base where_clause, considering final_limit)
        order_clause = ""
        if randomize is None:
            # Backwards-compatible: randomize if limit is provided
            if final_limit is not None and final_limit > 0:
                order_clause = f" ORDER BY {self.get_random_function()}"
        else:
            if randomize:
                order_clause = f" ORDER BY {self.get_random_function()}"

        # Build full SQL query with WHERE, ORDER BY, LIMIT, OFFSET
        sql_where = where_clause + order_clause
        if final_limit is not None and final_limit > 0:
            sql_where += f" LIMIT {int(final_limit)}"
        if offset is not None:
            sql_where += f" OFFSET {int(offset)}"

        try:
            result = self.get_db().select("proxies", "*", sql_where, params)
            return cast(List[Dict[str, Union[str, None]]], result)
        except Exception:
            # fallback: try without filters
            try:
                result = self.get_db().select("proxies", "*")
                return cast(List[Dict[str, Union[str, None]]], result)
            except Exception:
                return []

    def remove(self, proxy: Optional[str], delete_from_added: bool = False):
        if not proxy:
            return
        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            self.get_db().delete("proxies", "proxy = %s", [proxy.strip()])
        else:
            self.get_db().delete("proxies", "proxy = ?", [proxy.strip()])
        # also remove from added_proxies to allow re-adding if needed
        if delete_from_added:
            try:
                if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
                    self.get_db().execute_query(
                        "DELETE FROM added_proxies WHERE proxy = %s", [proxy.strip()]
                    )
                else:
                    self.get_db().execute_query(
                        "DELETE FROM added_proxies WHERE proxy = ?", [proxy.strip()]
                    )
            except Exception:
                # MySQL may use different placeholders; try with %s
                try:
                    self.get_db().execute_query(
                        "DELETE FROM added_proxies WHERE proxy = %s", [proxy.strip()]
                    )
                except Exception:
                    pass

    def add(self, proxy: str):
        proxy = self.normalize_proxy(proxy)
        if not proxy:
            return []
        # Do not add if present in added_proxies
        try:
            if self.is_already_added(proxy):
                return self.select(proxy)
        except Exception:
            # If the check fails for any reason, continue with normal flow
            pass

        sel = self.select(proxy)
        if not sel:
            # try to insert with default status
            try:
                self.get_db().insert(
                    "proxies", {"proxy": proxy.strip(), "status": "untested"}
                )
            except Exception:
                # fallback to minimal insert
                try:
                    self.get_db().insert("proxies", {"proxy": proxy.strip()})
                except Exception:
                    pass
            # mark as added if possible
            try:
                self.mark_as_added(proxy)
            except Exception:
                pass
        else:
            # keep silent
            pass
        return self.select(proxy)

    def update(
        self,
        proxy,
        proxy_type=None,
        region=None,
        city=None,
        country=None,
        status=None,
        latency=None,
        timezone=None,
        **kwargs,
    ):
        proxy = self.normalize_proxy(proxy)
        if not proxy:
            return
        if not self.select(proxy):
            self.add(proxy)
        data = {}
        if city:
            data["city"] = city
        if country:
            data["country"] = country
        if proxy_type:
            data["type"] = proxy_type
        if kwargs.get("type"):
            data["type"] = kwargs.get("type")
        if kwargs.get("type_"):
            data["type"] = kwargs.get("type_")
        if region:
            data["region"] = region
        if latency:
            data["latency"] = latency
        if timezone:
            data["timezone"] = timezone
        if status and status != "untested":
            data["status"] = status
            data["last_check"] = datetime.now().strftime("%Y-%m-%dT%H:%M:%S")
        if data:
            self.update_data(proxy, data)

    def update_data(
        self,
        proxy: str,
        data: Optional[Dict[str, Any]] = None,
        update_time: bool = True,
        debug: bool = False,
    ):
        debug_prefix = f"[{self.driver}]"
        proxy = self.normalize_proxy(proxy)
        if not proxy:
            if debug:
                print(f"{debug_prefix} Invalid proxy format, cannot update: {proxy}")
            return

        if not self.select(proxy):
            if debug:
                print(f"{debug_prefix} Proxy not found in database, adding: {proxy}")
            self.get_db().insert_ignore(
                "proxies", {"proxy": proxy.strip(), "status": "untested"}
            )

        if data is None:
            data = {}

        data = {key: value for key, value in data.items()}

        if (
            "status" in data
            and data.get("status") != "untested"
            and "last_check" not in data
            and update_time
        ):
            if debug:
                print(
                    f"{debug_prefix} Auto-updating last_check for proxy {proxy} with status {data.get('status')}"
                )
            data["last_check"] = get_current_rfc3339_time()

        if data:
            data = self.clean_type(data)
            data = self.fix_no_such_column(data)
            # sanitize values for SQL drivers (MySQL in particular)
            if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
                for k, v in list(data.items()):
                    # convert complex types to JSON strings
                    if isinstance(v, (list, dict)):
                        try:
                            data[k] = json.dumps(v, ensure_ascii=False)
                        except Exception:
                            data[k] = str(v)
                    elif isinstance(v, bytes):
                        try:
                            data[k] = v.decode("utf-8", "ignore")
                        except Exception:
                            data[k] = str(v)
                    else:
                        # leave scalars as-is
                        pass
            if debug:
                print(f"{debug_prefix} Updating proxy {proxy} with data: {data}")
            # use correct placeholder depending on backend
            if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
                self.get_db().update("proxies", data, "proxy = %s", [proxy.strip()])
            else:
                self.get_db().update("proxies", data, "proxy = ?", [proxy.strip()])

        # Auto mark as added after updating
        try:
            self.mark_as_added(proxy)
        except Exception:
            pass

    def fix_no_such_column(self, item: Dict[str, Any]):
        """Fix no such table column"""
        if not item.get("country") and item.get("country_name"):
            item["country"] = item.get("country_name")
        if not item.get("region") and item.get("region_name"):
            item["region"] = item.get("region_name")
        if not item.get("region") and item.get("region_code"):
            item["region"] = item.get("region_code")
        for key in ["region_name", "country_name", "country_code", "region_code"]:
            item.pop(key, None)
        return item

    def update_status(self, proxy: str, status: str):
        self.update(proxy.strip(), status=status)

    def update_latency(self, proxy, latency):
        self.update(proxy.strip(), latency=latency)

    def get_working_proxies(
        self,
        auto_fix: bool = False,
        limit: Optional[int] = None,
        randomize: bool = True,
        ssl: Optional[bool] = None,
        tun2socks: Optional[bool] = None,
        proxy_type: Optional[str] = None,
        page: Optional[int] = None,
        per_page: Optional[int] = None,
        last_checked: Optional[str] = None,
        output_file: Optional[Union[str, Path]] = None,
    ) -> List[Dict[str, Union[str, None]]]:
        """
        Retrieve working (active) proxies with optional limit, ordering and filters.

        Parameters
        - auto_fix (bool): If True, run `fix_empty_data()` on the results before
            returning to populate missing geo/webgl/useragent data.
        - limit (Optional[int]): Legacy single-argument limit (kept for compatibility).
        - randomize (bool): When True results are ordered randomly. When False
            results prefer most-recent rows (`ORDER BY rowid DESC` for SQLite,
            `ORDER BY id DESC` for MySQL) so recently added/updated proxies
            appear in limited result sets.
        - ssl (Optional[bool]): Filter by the `https` column:
                - `True`  => return only proxies where `https` represents SSL
                    (accepted values: "true", "1" — case-insensitive).
                - `False` => return only non-SSL proxies (NULL, empty string,
                    "false", "0").
                - `None`  => no https/ssl filtering (default).
        - tun2socks (Optional[bool]): Filter by the `tun2socks` column:
                - `True`  => return only proxies where `tun2socks` is numeric > 0.
                - `False` => return only proxies where `tun2socks` is NULL/empty
                    or numeric <= 0.
                - `None`  => no tun2socks filtering (default).
        - proxy_type (Optional[str]): Filter by the `type` column using LIKE.
            Useful for rows storing combined values like
            `http-socks4-socks4a-socks5-socks5h`.
        - page (Optional[int]): 1-based page number for pagination. If provided
            together with `per_page`, it overrides legacy `limit`.
        - per_page (Optional[int]): Number of items per page for pagination.
        - last_checked (Optional[str]): Filter by `last_check` column (RFC3339
            date string). When provided, only returns proxies with
            `last_check <= last_checked`.
        - output_file (Optional[Union[str, Path]]): If provided, saves the results
            as JSON to the specified file path.

        Returns
        - List[Dict[str, Union[str, None]]]: List of proxy rows as dictionaries.

        Notes
        - The `https` column is stored as TEXT and may contain different
            string representations; the method compares lowercase text and
            common numeric values to be robust.
        - MySQL and SQLite use different placeholder styles; callers should
            not need to format SQL themselves — use this method's filter
            arguments instead of manual WHERE building.
        - Pagination (page/per_page) takes precedence over legacy `limit`.
        """
        if limit is None:
            limit = sys.maxsize

        # Build backend-specific query and params
        params: List[Union[str, int]] = ["active"]
        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            placeholder = "%s"
            # For MySQL: when randomize use RAND(), otherwise prefer most-recent rows
            order_clause = " ORDER BY RAND()" if randomize else " ORDER BY id DESC"
        else:
            placeholder = "?"
            order_clause = " ORDER BY RANDOM()" if randomize else ""

        where_clause = f"status = {placeholder}"

        # SSL filtering: accept several stored representations
        # True -> only https values representing true ("true", "1")
        # False -> non-ssl (NULL, empty string, "false", "0")
        if ssl:
            # check lowercase and numeric 1
            where_clause += (
                f" AND (LOWER(https) = {placeholder} OR https = {placeholder})"
            )
            params.extend(["true", "1"])
        elif ssl is False:
            where_clause += f" AND (https IS NULL OR https = {placeholder} OR LOWER(https) = {placeholder} OR https = {placeholder})"
            params.extend(["", "false", "0"])

        if tun2socks:
            where_clause += " AND (tun2socks + 0) > 0"
        elif tun2socks is False:
            where_clause += (
                " AND (tun2socks IS NULL OR tun2socks = '' OR (tun2socks + 0) <= 0)"
            )

        if proxy_type and proxy_type.strip():
            where_clause += f" AND LOWER(type) LIKE {placeholder}"
            params.append(f"%{proxy_type.strip().lower()}%")

        # Last checked filtering (last_check <= last_checked)
        if last_checked:
            where_clause += f" AND last_check <= {placeholder}"
            params.append(last_checked)

        # For SQLiteHelper we can pass rand and limit separately to avoid
        # embedding LIMIT into the where string. For MySQL keep previous behavior.
        # Pagination (page/per_page) takes precedence over legacy limit
        offset = None
        final_limit = limit
        if page is not None and per_page is not None:
            page = max(1, int(page))
            per_page = max(0, int(per_page))
            offset = (page - 1) * per_page
            final_limit = per_page

        try:
            if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
                sql_where = f"{where_clause}{order_clause} LIMIT {int(final_limit)}"
                if offset is not None:
                    sql_where += f" OFFSET {int(offset)}"
                result = self.get_db().select("proxies", "*", sql_where, params)
            else:
                # sqlite: when not randomizing, prefer most-recent rows so newly
                # added/updated proxies appear in the limited result set.
                if not randomize:
                    where_clause = f"{where_clause} ORDER BY rowid DESC"
                # Build SQL with LIMIT/OFFSET for SQLite
                limit_offset_sql = f"LIMIT {int(final_limit)}"
                if offset is not None:
                    limit_offset_sql += f" OFFSET {int(offset)}"
                if randomize:
                    limit_offset_sql = f"ORDER BY RANDOM() {limit_offset_sql}"
                full_where = f"{where_clause} {limit_offset_sql}"
                result = self.get_db().select("proxies", "*", full_where, params)
        except Exception:
            result = []

        if not result:
            return []

        result = cast(List[Dict[str, Union[str, None]]], result)

        if auto_fix:
            result = self.fix_empty_data(result)

        if output_file:
            try:
                output_path = Path(output_file)
                output_path.parent.mkdir(parents=True, exist_ok=True)
                with open(output_path, "w", encoding="utf-8") as f:
                    json.dump(result, f, indent=2, ensure_ascii=False)
            except Exception as e:
                print(f"Error writing results to {output_file}: {e}")

        return result

    def clean_type(
        self, item: Dict[str, Union[str, None]]
    ) -> Dict[str, Union[str, None]]:
        if "type" in item:
            type_value = item.get("type")
            if type_value is not None and type_value != "":
                types = type_value.split("-")
                cleaned_types = [t for t in types if t]  # Filter out empty strings
                item["type"] = "-".join(cleaned_types)  # Re-merge with hyphen
            else:
                item["type"] = type_value  # Preserve None or empty string
        return item

    def clean_types(
        self, data: List[Dict[str, Union[str, None]]]
    ) -> List[Dict[str, Union[str, None]]]:
        """
        Clean and merge the 'type' values in a list of dictionaries.

        Args:
        - data (List[Dict[str, Union[str, None]]]): A list of dictionaries where each dictionary
        contains a 'type' key that can be a string or None.

        Returns:
        - List[Dict[str, Union[str, None]]]: The cleaned list of dictionaries with 'type' values
        cleaned and merged with hyphens where applicable.
        """
        return [self.clean_type(item) for item in data]

    def get_untested_proxies(
        self, limit: Optional[int] = None, randomize: bool = True
    ) -> List[Dict[str, Union[str, None]]]:
        if not limit:
            limit = sys.maxsize

        # Desired semantics: include rows where status is NULL or empty string,
        # and also any row whose status is NOT one of ('active','port-closed','dead').
        params: List[Union[str, int]]
        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            order_clause = " ORDER BY RAND()" if randomize else ""
            sql_where = f"status IS NULL OR status = %s OR status NOT IN (%s, %s, %s){order_clause} LIMIT {limit}"
            params = ["", "active", "port-closed", "dead"]
            result = self.get_db().select("proxies", "*", sql_where, params)
        else:
            order_clause = " ORDER BY RANDOM()" if randomize else ""
            sql_where = f"status IS NULL OR status = ? OR status NOT IN (?,?,?){order_clause} LIMIT {limit}"
            params = ["", "active", "port-closed", "dead"]
            result = self.get_db().select("proxies", "*", sql_where, params)

        if not result:
            return []
        return cast(List[Dict[str, Union[str, None]]], result)

    def get_private_proxies(self) -> List[Dict[str, Union[str, None]]]:
        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            result = self.get_db().select("proxies", "*", "status = %s", ["private"])
        else:
            result = self.get_db().select("proxies", "*", "status = ?", ["private"])
        if not result:
            return []
        return cast(List[Dict[str, Union[str, None]]], result)

    def get_dead_proxies(
        self, limit: Optional[int] = None, randomize: bool = True
    ) -> List[Dict[str, Union[str, None]]]:
        """Return dead or port-closed proxies.

        Args:
            limit: maximum number of results to return.
            randomize: when True include a random ordering clause in the SQL.
        """
        if not limit:
            limit = sys.maxsize

        if isinstance(self.db, MySQLHelper) or self.driver == "mysql":
            order_clause = " ORDER BY RAND()" if randomize else ""
            sql = f"status = %s or status = %s{order_clause} LIMIT {limit}"
            result = self.get_db().select(
                "proxies",
                "*",
                sql,
                ["dead", "port-closed"],
            )
        else:
            order_clause = " ORDER BY RANDOM()" if randomize else ""
            sql = f"status = ? or status = ?{order_clause} LIMIT {limit}"
            result = self.get_db().select(
                "proxies",
                "*",
                sql,
                ["dead", "port-closed"],
            )
        if not isinstance(result, list):
            result = []
        return cast(List[Dict[str, Union[str, None]]], result)

    def count_by_status(self) -> List[Dict[str, Union[str, int]]]:
        """
        Return a list of dicts with status and count of proxies for that status.

        Example: [{"status": "active", "count": 123}, ...]
        """
        try:
            sql_where = "1=1 GROUP BY status"
            # Use same select signature as other methods: table, columns, where, params
            result = self.get_db().select(
                "proxies", "status, COUNT(*) AS count", sql_where, []
            )
            if not result:
                return []
            out: List[Dict[str, Union[str, int]]] = []
            for row in result:
                status_val = row.get("status")
                # DB may return count as int or str; normalize to int
                raw_count = row.get("count")
                try:
                    count_val = int(raw_count) if raw_count is not None else 0
                except Exception:
                    try:
                        count_val = int(str(raw_count))
                    except Exception:
                        count_val = 0
                out.append(
                    {
                        "status": status_val if status_val is not None else "",
                        "count": count_val,
                    }
                )
            return out
        except Exception:
            return []

    def fix_empty_data(self, results: List[Dict[str, Union[str, None]]]):
        if not results:
            return []
        return [{**item, **self.fix_empty_single_data(item)} for item in results]

    def fix_empty_single_data(
        self, item: Dict[str, Union[str, None]]
    ) -> Dict[str, Union[str, None]]:
        db_data = item
        modify = False
        if (
            not item.get("country")
            or not item.get("timezone")
            or not item.get("longitude")
            or not item.get("latitude")
        ):
            _proxy = item.get("proxy")
            if _proxy:
                geo = get_geo_ip(_proxy)
                if geo:
                    modify = True
                    db_data.update(
                        {
                            k: v
                            for k, v in {
                                "country": geo.country_name,
                                "lang": geo.lang,
                                "timezone": geo.timezone,
                                "region": geo.region_name,
                                "city": geo.city,
                                "longitude": geo.longitude,
                                "latitude": geo.latitude,
                            }.items()
                            if v is not None
                        }
                    )

        if (
            not item.get("webgl_renderer")
            or not item.get("webgl_vendor")
            or not item.get("browser_vendor")
        ):
            modify = True
            webgl = random_webgl_data()
            db_data.update(
                {
                    "webgl_vendor": webgl.webgl_vendor,
                    "browser_vendor": webgl.browser_vendor,
                    "webgl_renderer": webgl.webgl_renderer,
                }
            )
        if not item.get("useragent"):
            modify = True
            db_data["useragent"] = random_windows_ua()
        db_data = self.clean_type(db_data)
        if modify:
            _proxy = item.get("proxy")
            if _proxy:
                self.update_data(_proxy, db_data)
        return db_data

    def from_file(
        self,
        filePath: str,
        callback: Callable[[Proxy], None],
        limit: Union[int, None] = None,
    ) -> None:
        """
        Read a file and parse each line to extract IP, port, username, and password.

        Args:
            filePath (str): The path to the file to read.
            callback (Callable[[dict], None]): A callback function to be called with each parsed result.
            limit (Optional[int]): Maximum number of lines to parse. If None, parse all lines.
        """
        if not os.path.exists(filePath):
            print(f"File '{filePath}' does not exist.")
            return

        counter = 0
        with open(filePath, "r", encoding="utf-8") as file:
            for line in file:
                line = line.strip()
                if not line:  # Skip empty lines
                    continue
                if limit is not None and counter >= limit:
                    break
                counter += 1
                parsed_data = self.extract_proxies(line)
                if parsed_data:
                    for single_data in parsed_data:
                        callback(single_data)

    def extract_proxies(
        self, line: Optional[str], update_db: Optional[bool] = True, debug: bool = False
    ) -> List[Proxy]:
        """
        Parse a line to extract IP, port, username, and password.

        Args:
            line: A string containing IP:PORT[@username:password].

        Returns:
            A list of Proxy objects containing IP, port, username (if present), and password (if present).
        """
        if not line or not line.strip():
            return []

        result = extract_proxies(line)
        if update_db:
            for item in result:
                if not self.select(item.proxy):
                    if debug:
                        print(f"[extract_proxies] Adding proxy: {item.proxy}")
                    self.add(item.proxy)
                if item.username and item.password:
                    if debug:
                        print(
                            f"[extract_proxies] Updating credentials for proxy: {item.proxy}"
                        )
                    self.update_data(
                        item.proxy,
                        {"username": item.username, "password": item.password},
                    )

        return result

    def checksum(self, table: str, columns: List[str]) -> str:
        return self.get_db().checksum(table, columns)
