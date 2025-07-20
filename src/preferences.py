import os
import json
from typing import Any, Optional


class PreferencesHelper:
    """
    A helper class to manage user preferences stored in a JSON file.

    Attributes:
        file_path (str): Path to the preferences file.
        preferences (dict): Dictionary to store preferences.
    """

    def __init__(self, file_path: str) -> None:
        """
        Initializes the PreferencesHelper with the path to the preferences file.

        Args:
            file_path (str): The path to the JSON file where preferences are stored.
        """
        self.file_path = file_path
        self._ensure_folder_exists()
        self.data = self._load()

    def _ensure_folder_exists(self) -> None:
        """
        Ensures that the folder path for the preferences file exists.
        Creates the folder(s) recursively if they do not exist.
        """
        folder_path = os.path.dirname(self.file_path)
        if folder_path and not os.path.exists(folder_path):
            try:
                os.makedirs(folder_path, exist_ok=True)
            except OSError as e:
                print(f"Error creating directory {folder_path}: {e}")

    def _load(self) -> dict:
        """
        Loads preferences from the JSON file.

        Returns:
            dict: The loaded preferences as a dictionary.
        """
        try:
            with open(self.file_path, "r") as file:
                return json.load(file)
        except FileNotFoundError:
            return {}
        except json.JSONDecodeError as e:
            print(f"Error loading preferences: {e}")
            return {}

    def save(self) -> None:
        """
        Saves the current preferences to the JSON file.
        """
        try:
            with open(self.file_path, "w") as file:
                json.dump(self.data, file, indent=4)
        except IOError as e:
            print(f"Error saving preferences: {e}")

    def get(self, key: str, default: Optional[Any] = None) -> Any:
        """
        Retrieves a preference value by key, returning a default value if the key is not found.

        Args:
            key (str): The preference key.
            default (Any, optional): The default value to return if the key is not found.

        Returns:
            Any: The value associated with the key, or the default value.
        """
        return self.data.get(key, default)

    def set(self, key: str, value: Any) -> None:
        """
        Sets a preference value by key.

        Args:
            key (str): The preference key.
            value (Any): The value to set for the preference.
        """
        self.data[key] = value
        self.save()

    def remove(self, key: str) -> None:
        """
        Removes a preference by key.

        Args:
            key (str): The preference key to remove.
        """
        if key in self.data:
            del self.data[key]
            self.save()

    def reset(self) -> None:
        """
        Resets all preferences by clearing the preferences dictionary and saving.
        """
        self.data.clear()
        self.save()


# Usage Example
if __name__ == "__main__":
    prefs = PreferencesHelper("tmp/user_preferences.json")

    # Set a preference
    prefs.set("theme", "dark")

    # Get a preference
    theme = prefs.get("theme", "light")
    print(f"Current theme: {theme}")

    # Remove a preference
    prefs.remove("theme")

    # Reset all preferences
    prefs.reset()
