import json
import random
from typing import Dict, List, Optional, Union
from filelock import FileLock
from src.func import get_relative_path, get_unique_dicts_by_key_in_list
from src.func_proxy import upload_proxy
from src.ProxyDB import ProxyDB


class ProxyWorkingManager:
    def __init__(self):
        self.filename: str = get_relative_path("working.json")
        self.lock: FileLock = FileLock(get_relative_path("tmp/workload.lock"))
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
        self.data = get_unique_dicts_by_key_in_list(
            list(merged_entries.values()), "proxy"
        )
        self._save_data()

    def _load_data(self) -> List[Dict[str, Union[str, int, float]]]:
        """Load data from JSON file."""
        try:
            with open(self.filename, "r", encoding="utf-8") as file:
                return json.load(file)
        except (FileNotFoundError, json.JSONDecodeError) as e:
            print(f"Error loading data: {e}")
            return []

    def _save_data(self) -> None:
        """Save data to JSON file."""
        with self.lock:
            working_data = [
                {key: (value if value else None) for key, value in item.items()}
                for item in self.data
            ]
            with open(self.filename, "w", encoding="utf-8") as file:
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

        # Using a dictionary for faster lookups
        entry_map = {entry.get("proxy"): entry for entry in self.data}
        if proxy_value in entry_map:
            entry_map[proxy_value] = updated_entry
            self.data = list(entry_map.values())
            self._save_data()

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
        entry_map = {entry.get("proxy"): entry for entry in self.data}
        if proxy_value in entry_map:
            del entry_map[proxy_value]
            self.data = list(entry_map.values())
            self._save_data()

    def upload_proxies(self) -> None:
        """Upload working proxies."""
        formatted = [
            (
                f"{data.get('username')}:{data.get('password')}@{data.get('proxy')}"
                if data.get("username") and data.get("password")
                else data.get("proxy")
            )
            for data in self.data
        ]
        if formatted:
            upload_proxy(json.dumps(formatted))


if __name__ == "__main__":
    manager = ProxyWorkingManager()
    manager._load_db()
    manager.upload_proxies()
