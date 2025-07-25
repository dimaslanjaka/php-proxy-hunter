import os
import sys
import time
from datetime import datetime
from typing import Any, Callable, Dict, List, Optional, Union

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


class ProxyDB:
    """
    ProxyDB manages proxy data storage and operations using an SQLite database.

    Attributes:
        db (SQLiteHelper): The SQLiteHelper instance for database operations. This attribute is available after `start_connection()` has been called at least once.

    Note:
        Ensure `start_connection()` is called before accessing or using `self.db` to establish the database connection.
    """

    db: Optional[SQLiteHelper] = None

    def __init__(
        self,
        db_location: Optional[str] = None,
        start: bool = False,
        check_same_thread: bool = False,
    ):
        """
        Initialize ProxyDB instance.

        Args:
            db_location (Optional[str]): The location of the SQLite database file. If None, uses default path.
            start (bool): If True, automatically starts the database connection.
        """
        self.check_same_thread = check_same_thread
        self.db_location = db_location
        self.db: Optional[SQLiteHelper] = None
        if db_location is None:
            self.db_location = get_relative_path("src/database.sqlite")
        if start:
            self.start_connection()

    def start_connection(self):
        """Establishes a connection to the SQLite database and sets up initial configurations."""
        try:
            if not self.db_location:
                self.db_location = get_relative_path("src/database.sqlite")
            self.db = SQLiteHelper(
                self.db_location, check_same_thread=self.check_same_thread
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

            wal_enabled = self.get_meta_value("wal_enabled")
            if not wal_enabled:
                self.db.execute_query("PRAGMA journal_mode = WAL")
                self.db.execute_query("PRAGMA wal_autocheckpoint = 100")
                self.set_meta_value("wal_enabled", "1")

            auto_vacuum_enabled = self.get_meta_value("auto_vacuum_enabled")
            if not auto_vacuum_enabled:
                self.db.execute_query("PRAGMA auto_vacuum = FULL")
                self.set_meta_value("auto_vacuum_enabled", "1")

            self.run_daily_vacuum()
        except Exception as e:
            file_append_str(get_nuitka_file("error.txt"), str(e))
            print(e)

    def close(self):
        """Closes the database connection if open."""
        if self.db:
            try:
                if self.db.cursor:
                    self.db.cursor.close()
                if self.db.conn:
                    self.db.conn.close()
                self.db.close()
            except Exception as e:
                print(f"cannot close database: {e}")

    def get_meta_value(self, key: str) -> Optional[str]:
        """
        Retrieves a meta value from the database.

        Args:
            key (str): The key for which to retrieve the value.

        Returns:
            Optional[str]: The meta value associated with the key, or None if not found.
        """
        if not self.db:
            self.start_connection()
        result = self.db.select("meta", "value", "key = ?", (key,))
        return result[0]["value"] if result else None

    def set_meta_value(self, key: str, value: str) -> None:
        """
        Sets a meta value in the database.

        Args:
            key (str): The key to set.
            value (str): The value to set.
        """
        if not self.db:
            self.start_connection()
        sql = "REPLACE INTO meta (key, value) VALUES (?, ?)"
        self.db.execute_query(sql, (key, value))

    def run_daily_vacuum(self):
        last_vacuum_time: Optional[str] = self.get_meta_value("last_vacuum_time")
        current_time: int = int(time.time())
        one_day_in_seconds: int = 86400

        if not last_vacuum_time or (
            current_time - int(last_vacuum_time) > one_day_in_seconds
        ):
            self.db.execute_query("VACUUM")
            # https://stackoverflow.com/a/37865221/6404439
            self.db.execute_query("PRAGMA wal_checkpoint(SQLITE_CHECKPOINT_TRUNCATE);")
            self.set_meta_value("last_vacuum_time", str(current_time))

    def select(self, proxy: str):
        if not self.db:
            self.start_connection()
        return self.db.select("proxies", "*", "proxy = ?", [proxy.strip()])

    def get_all_proxies(
        self, rand: Optional[bool] = False
    ) -> List[Dict[str, Union[str, None]]]:
        if not self.db:
            self.start_connection()
        return self.db.select("proxies", "*", rand=rand)

    def remove(self, proxy):
        if not self.db:
            self.start_connection()
        self.db.delete("proxies", "proxy = ?", [proxy.strip()])

    def add(self, proxy: str):
        sel = self.select(proxy)
        if len(sel) == 0:
            self.db.insert("proxies", {"proxy": proxy.strip()})
        else:
            print(f"proxy {proxy} already exists")
        return self.select(proxy)

    def update(
        self,
        proxy,
        type_=None,
        region=None,
        city=None,
        country=None,
        status=None,
        latency=None,
        timezone=None,
    ):
        if not self.select(proxy):
            self.add(proxy)
        data = {}
        if city:
            data["city"] = city
        if country:
            data["country"] = country
        if type_:
            data["type"] = type_
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
            self.db.update("proxies", data, "proxy = ?", [proxy.strip()])

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
        self, auto_fix: bool = True
    ) -> List[Dict[str, Union[str, None]]]:
        if not self.db:
            self.start_connection()
        result = self.db.select("proxies", "*", "status = ?", ["active"])
        if auto_fix:
            return self.fix_empty_data(result)
        else:
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
        self, limit: Optional[int] = None
    ) -> List[Dict[str, Union[str, None]]]:
        if not limit:
            limit = sys.maxsize
        if not self.db:
            self.start_connection()
        result = self.db.select(
            "proxies",
            "*",
            f"status IS NULL OR status = ? OR status = ? ORDER BY RANDOM() LIMIT {limit}",
            ["untested", ""],
        )
        if not result:
            return []
        return result

    def get_private_proxies(self) -> List[Dict[str, Union[str, None]]]:
        if not self.db:
            self.start_connection()
        result = self.db.select("proxies", "*", "status = ?", ["private"])
        if not result:
            return []
        return result

    def get_dead_proxies(
        self, limit: Optional[int] = None
    ) -> List[Dict[str, Union[str, None]]]:
        if not limit:
            limit = sys.maxsize
        if not self.db:
            self.start_connection()
        result = self.db.select(
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
        self, line: Optional[str], update_db: Optional[bool] = True
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
                    self.add(item.proxy)
                if item.username and item.password:
                    self.update_data(
                        item.proxy,
                        {"username": item.username, "password": item.password},
                    )

        return result
