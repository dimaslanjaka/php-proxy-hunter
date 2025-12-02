import os
import sys
import time
from datetime import datetime
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
from src.geoPlugin import get_geo_ip2
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
        self.db_type = db_type.lower() if db_type else "sqlite"
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
            if self.db_type != "mysql":
                self.db_location = get_relative_path("src/database.sqlite")
            else:
                self.db_location = None
        if start:
            self.start_connection()

    def start_connection(self):
        """Establishes a connection to the SQLite database and sets up initial configurations."""
        try:
            # Decide backend
            if self.db_type == "mysql":
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
                    sql_file = get_relative_path(
                        "src/PhpProxyHunter/assets/mysql-schema.sql"
                    )
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
            if self.db_type != "mysql":
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
        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
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

        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
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

    def select(self, proxy: str):
        proxy = self.normalize_proxy(proxy)
        # both helpers accept select(table, columns, where, params, rand, limit, offset) loosely
        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
            return self.get_db().select("proxies", "*", "proxy = %s", [proxy.strip()])
        else:
            return self.get_db().select("proxies", "*", "proxy = ?", [proxy.strip()])

    def is_already_added(self, proxy: Optional[str]) -> bool:
        proxy = self.normalize_proxy(proxy)
        try:
            if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
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
        if self.is_already_added(proxy):
            return
        try:
            if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
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
        data = proxy.strip()
        result = extract_proxies(data)
        if not result:
            return data
        # If there are multiple proxies, return first (PHP throws) — keep simple here
        return result[0].proxy

    def get_all_proxies(
        self,
        limit: Optional[int] = None,
        randomize: Optional[bool] = None,
        page: Optional[int] = None,
        per_page: Optional[int] = None,
    ) -> List[Dict[str, Union[str, None]]]:
        """Get all proxies with optional pagination and randomization.

        Backwards-compatible: when only `limit` is provided it behaves like before
        (providing a positive limit implies randomization unless `randomize` is set).
        """
        # Determine ordering
        if randomize is None:
            order_by = (
                self.get_random_function()
                if (limit is not None and limit > 0)
                else None
            )
        else:
            order_by = self.get_random_function() if randomize else None

        # Pagination
        offset = None
        final_limit = limit
        if page is not None and per_page is not None:
            page = max(1, int(page))
            per_page = max(0, int(per_page))
            offset = (page - 1) * per_page
            final_limit = per_page

        # MySQLHelper.select signature supports orderBy, limit, offset as extra args maybe
        try:
            # prefer keyword-style where supported
            return self.get_db().select("proxies", "*", None, [], order_by, final_limit, offset)  # type: ignore[arg-type]
        except TypeError:
            # fallback to simple select without pagination/order
            return self.get_db().select("proxies", "*")

    def remove(self, proxy):
        proxy = self.normalize_proxy(proxy)
        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
            self.get_db().delete("proxies", "proxy = %s", [proxy.strip()])
        else:
            self.get_db().delete("proxies", "proxy = ?", [proxy.strip()])
        # also remove from added_proxies if table exists
        try:
            if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
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

    def update_data(self, proxy: str, data: Optional[Dict[str, Any]] = None):
        if not proxy.strip() or not self.select(proxy):
            self.add(proxy)

        if data is None:
            data = {}

        data = {key: value for key, value in data.items()}

        if "status" in data and data.get("status") != "untested":
            data["last_check"] = get_current_rfc3339_time()

        if data:
            data = self.clean_type(data)
            data = self.fix_no_such_column(data)
            # print(data)
            # use correct placeholder depending on backend
            if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
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
        auto_fix: bool = True,
        limit: Optional[int] = None,
        randomize: bool = True,
    ) -> List[Dict[str, Union[str, None]]]:
        """
        Retrieve working (active) proxies with optional limit and randomization.

        Args:
            auto_fix: If True, run `fix_empty_data` on results before returning.
            limit: Optional maximum number of returned rows. None means no limit.
            randomize: If True, order results randomly.
        """
        if limit is None:
            limit = sys.maxsize

        # Build backend-specific query
        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
            order_clause = " ORDER BY RAND()" if randomize else ""
            sql_where = f"status = %s{order_clause} LIMIT {int(limit)}"
            result = self.get_db().select("proxies", "*", sql_where, ["active"])
        else:
            order_clause = " ORDER BY RANDOM()" if randomize else ""
            sql_where = f"status = ?{order_clause} LIMIT {int(limit)}"
            result = self.get_db().select("proxies", "*", sql_where, ["active"])

        if not result:
            return []

        if auto_fix:
            return self.fix_empty_data(result)
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

        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
            order_clause = f" ORDER BY RAND()" if randomize else ""
            result = self.get_db().select(
                "proxies",
                "*",
                f"status IS NULL OR status = %s OR status = %s OR status = %s OR status = %s{order_clause} LIMIT {limit}",
                ["untested", "", "port-open", "open-port"],
            )
        else:
            order_clause = f" ORDER BY RANDOM()" if randomize else ""
            result = self.get_db().select(
                "proxies",
                "*",
                f"status IS NULL OR status = ? OR status = ? OR status = ? OR status = ?{order_clause} LIMIT {limit}",
                ["untested", "", "port-open", "open-port"],
            )
        if not result:
            return []
        return result

    def get_private_proxies(self) -> List[Dict[str, Union[str, None]]]:
        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
            result = self.get_db().select("proxies", "*", "status = %s", ["private"])
        else:
            result = self.get_db().select("proxies", "*", "status = ?", ["private"])
        if not result:
            return []
        return result

    def get_dead_proxies(
        self, limit: Optional[int] = None
    ) -> List[Dict[str, Union[str, None]]]:
        if not limit:
            limit = sys.maxsize

        if isinstance(self.db, MySQLHelper) or self.db_type == "mysql":
            result = self.get_db().select(
                "proxies",
                "*",
                f"status = %s or status = %s ORDER BY RAND() LIMIT {limit}",
                ["dead", "port-closed"],
            )
        else:
            result = self.get_db().select(
                "proxies",
                "*",
                f"status = ? or status = ? ORDER BY RANDOM() LIMIT {limit}",
                ["dead", "port-closed"],
            )
        if not isinstance(result, list):
            result = []
        return result

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
                geo = get_geo_ip2(_proxy)
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
