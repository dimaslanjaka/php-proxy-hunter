import json
import random
from typing import Dict, List, Optional, Union

from filelock import FileLock

from src.func import get_relative_path, get_unique_dicts_by_key_in_list
from src.func_proxy import upload_proxy
from src.ProxyDB import ProxyDB

working_file = get_relative_path("working.json")
lock_file = get_relative_path("tmp/workload.lock")


class ProxyWorkingManager:
    def __init__(self):
        global working_file
        self.filename: str = working_file
        self.lock: FileLock = FileLock(lock_file)
        self.data: List[Dict[str, Union[str, int, float]]] = self._load_data()
        self.db = ProxyDB(get_relative_path("src/database.sqlite"), True)

    def _load_db(self):
        """Import and merge from database into working.json"""
        db_data = self.db.db.select("proxies", "*", "status = ?", ["active"])
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

    def add_entry(self, entry: Optional[Dict[str, Union[str, int, float]]]) -> None:
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
        self,
        proxy_value: str,
        updated_entry: Optional[Dict[str, Union[str, int, float]]],
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

    def upload_proxies(self):
        """
        Upload working proxies
        """
        formatted: List[str] = []
        for data in self.data:
            if data.get("username") and data.get("password"):
                formatted.append(
                    "{}:{}@{}".format(
                        data.get("username"), data.get("password"), data.get("proxy")
                    )
                )
            else:
                formatted.append(data.get("proxy"))
        if formatted:
            upload_proxy(json.dumps(formatted))


# Example usage
if __name__ == "__main__":
    manager = ProxyWorkingManager()
    manager._load_db()
    manager.upload_proxies()
