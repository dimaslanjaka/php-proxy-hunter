import os
import re
import sys
from typing import List

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path

MD5_DIR_PATTERN = re.compile(r"^[a-f0-9]{32}$")


def fetch_md5_hash_dirs(path: str) -> List[str]:
    """List only directories whose basename is an MD5 hash."""
    try:
        return [
            os.path.join(path, d)
            for d in os.listdir(path)
            if os.path.isdir(os.path.join(path, d)) and MD5_DIR_PATTERN.fullmatch(d)
        ]
    except FileNotFoundError:
        return []


if __name__ == "__main__":
    # Clean logs
    logs_path = get_relative_path("tmp/logs")
    for log_dir in fetch_md5_hash_dirs(logs_path):
        print(f"Processing log directory: {log_dir}")
