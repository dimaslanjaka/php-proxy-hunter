import json
import os
from typing import Any, Optional
from .folder import resolve_parent_folder


def write_json(file_path: str, data: Any) -> None:
    """
    Write JSON data to a file. Creates parent directories if they do not exist.

    Args:
        file_path: target file path
        data: JSON-serializable data
    """
    if not data:
        return

    # Ensure parent directories exist
    os.makedirs(os.path.dirname(file_path), exist_ok=True)

    with open(file_path, "w", encoding="utf-8") as file:
        json.dump(data, file, indent=2, ensure_ascii=False)


def write_file(file_path: Optional[str], content: Optional[str]) -> None:
    """
    Write content to a file, creating parent directories as needed.

    Args:
        file_path: path to write to
        content: string content to write (or None)
    """
    try:
        if file_path:
            resolve_parent_folder(file_path)
            with open(file_path, "w", encoding="utf-8") as file:
                file.write(content or "")
    except Exception as e:
        print(f"Error writing {file_path} - {e}")
