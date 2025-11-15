import os
import random
import shutil
import time
from pathlib import Path
from typing import List
from .permissions import fix_permissions


def resolve_folder(path: str) -> str:
    """Ensure a folder exists, set perms, and return the path."""
    resolve_parent_folder(path)
    os.makedirs(path, exist_ok=True)
    fix_permissions(path)
    return path


def resolve_parent_folder(path: str) -> str:
    """Ensure parent folder exists and return it."""
    parent_folder = os.path.dirname(path)
    if not os.path.exists(parent_folder):
        os.makedirs(parent_folder)
    fix_permissions(parent_folder)
    return parent_folder


def get_random_folder(directory: str) -> str:
    """Return a random subfolder path within directory."""
    if not os.path.isdir(directory):
        raise ValueError("The specified directory does not exist.")

    folders: List[str] = [
        os.path.join(directory, folder)
        for folder in os.listdir(directory)
        if os.path.isdir(os.path.join(directory, folder))
    ]

    if not folders:
        raise ValueError("There are no subdirectories in the specified directory.")

    return os.path.normpath(random.choice(folders))


def list_files_in_directory(directory: str) -> List[str]:
    """Return absolute file paths under directory."""
    if not os.path.exists(directory):
        return []

    file_paths = []
    for root, _, files in os.walk(directory):
        for file in files:
            file_paths.append(os.path.abspath(os.path.join(root, file)))

    return file_paths


def is_directory_created_days_ago_or_more(directory_path: str, days: int) -> bool:
    """Check whether directory was created/modified days ago or more."""
    if os.path.exists(directory_path):
        mod_time = os.path.getmtime(directory_path)
        current_time = time.time()
        return (current_time - mod_time) >= days * 24 * 60 * 60
    return False


def join_path(*segments: str) -> str:
    return str(Path(*segments).resolve())
