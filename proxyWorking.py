import random
import json
import os
from typing import List, Dict, Optional, Union
from src.ProxyDB import ProxyDB
from src.func import get_relative_path, get_unique_dicts_by_key_in_list
from filelock import FileLock

working_file = get_relative_path("working.json")
lock_file = get_relative_path("tmp/workload.lock")


class ProxyWorkingManager:
    def __init__(self):
        global working_file
        self.filename: str = working_file
        self.lock: FileLock = FileLock(lock_file)
        self.data: List[Dict[str, Union[str, int, float]]] = self._load_data()
        self.db: Optional["ProxyDB"] = None
        try:
            self.db = ProxyDB(get_relative_path("src/database.sqlite"), True)
        except Exception as e:
            print(f"Failed to initialize ProxyDB: {e}")

    def _load_db(self):
        """Import and merge from database into working.json"""
        db_data = self.db.get_working_proxies()
        file_data = self._load_data()
        # Create a dictionary for fast lookup
        existing_proxies = {entry.get("proxy"): entry for entry in file_data}
        new_entries_dict = {
            entry.get("proxy"): entry for entry in db_data if entry.get("proxy")
        }

        # Merge new entries, retaining the most recent data for each proxy
        merged_entries = {**existing_proxies, **new_entries_dict}

        # Update the data list with merged entries
        self.data = get_unique_dicts_by_key_in_list(
            list(merged_entries.values()), "proxy"
        )
        self._save_data()

    def _load_data(self) -> List[Dict[str, Union[str, int, float]]]:
        """Load data from JSON file."""
        try:
            with open(self.filename, "r", encoding="utf-8") as file:
                return json.load(file)
        except FileNotFoundError:
            return []
        except json.JSONDecodeError:
            return []

    def _save_data(self) -> None:
        """Save data to JSON file."""
        with self.lock:
            with open(self.filename, "w", encoding="utf-8") as file:
                # Process the list to replace empty values with None
                working_data = [
                    {key: (value if value else None) for key, value in item.items()}
                    for item in self.data
                ]
                json.dump(
                    get_unique_dicts_by_key_in_list(working_data, "proxy"),
                    file,
                    indent=2,
                )

    def add_entry(self, entry: Dict[str, Union[str, int, float]]) -> None:
        """Add a new entry to the data if 'proxy' key is unique."""
        if not isinstance(entry, dict):
            raise ValueError("Entry must be a dictionary.")

        proxy_value = entry.get("proxy")
        if not proxy_value:
            raise ValueError("Entry must have a 'proxy' key with a non-empty value.")

        # Check for duplicates
        if not any(
            existing_entry.get("proxy") == proxy_value for existing_entry in self.data
        ):
            self.data.append(entry)
            self._save_data()

    def update_entry(
        self, proxy_value: str, updated_entry: Dict[str, Union[str, int, float]]
    ) -> None:
        """Update an existing entry by 'proxy' key."""
        if not isinstance(updated_entry, dict):
            raise ValueError("Updated entry must be a dictionary.")

        # Find the index of the entry with the given proxy
        for index, entry in enumerate(self.data):
            if entry.get("proxy") == proxy_value:
                self.data[index] = updated_entry
                self._save_data()
                return

    def select_by_key(
        self, key: str, value: str
    ) -> List[Dict[str, Union[str, int, float]]]:
        """Select entries by a key-value pair."""
        return [entry for entry in self.data if entry.get(key) == value]

    def get_data(
        self, shuffle: Optional[bool] = False
    ) -> List[Dict[str, Union[str, int, float]]]:
        """Get the current data."""
        data = get_unique_dicts_by_key_in_list(self.data, "proxy")
        if shuffle:
            random.shuffle(data)
        return data

    def remove_entry(self, proxy_value: str) -> None:
        """Remove an entry by 'proxy' key."""
        for index, entry in enumerate(self.data):
            if entry.get("proxy") == proxy_value:
                del self.data[index]
                self._save_data()
                return


# Example usage
if __name__ == "__main__":
    manager = ProxyWorkingManager()

    # Add a new entry
    new_entry = {
        "proxy": "192.168.0.1:8080",
        "latency": "300",
        "last_check": "2024-08-01T12:00:00+0000",
        "type": "HTTPS",
        "region": "-",
        "city": "-",
        "country": "United Kingdom",
        "timezone": "Europe/London",
        "latitude": "51.509",
        "longitude": "-0.118",
        "anonymity": "-",
        "https": "true",
        "status": "active",
        "private": "-",
        "lang": "en_GB",
        "useragent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36",
        "webgl_vendor": "Google Inc.",
        "webgl_renderer": "ANGLE (Intel(R) HD Graphics 630 Direct3D11 vs_5_0 ps_5_0)",
        "browser_vendor": "Google Inc.",
        "username": "-",
        "password": "-",
    }
    manager.add_entry(new_entry)

    # Update an existing entry
    manager.update_entry(
        "192.168.0.1:8080", new_entry
    )  # Update the entry with the specified proxy

    # Select entries by key
    results = manager.select_by_key("country", "United Kingdom")
    print(results)
