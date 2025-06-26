import os
import shutil
from pathlib import Path
from typing import Optional


def get_binary_path(binary_name: str, base_dir: Optional[Path] = None) -> Optional[str]:
    """
    Locate a binary by name, prioritizing project-local environments.

    Search order:
      1. Python venv (bin/Scripts)
      2. node_modules/.bin
      3. vendor/bin (PHP Composer)
      4. System PATH

    Args:
        binary_name: Name of the binary (e.g., 'python', 'eslint', 'phpcs')
        base_dir: Base directory for local search. Defaults to current working directory.

    Returns:
        Full path to binary, or None if not found.
    """
    base_dir = Path(base_dir or Path.cwd()).resolve()
    is_windows = os.name == "nt"

    venv_dir = base_dir / "venv" / ("Scripts" if is_windows else "bin")
    node_bin_dir = base_dir / "node_modules" / ".bin"
    composer_bin_dir = base_dir / "vendor" / "bin"

    binary_ext = ".exe" if is_windows else ""
    node_ext = ".cmd" if is_windows else ""

    search_paths = [
        venv_dir / (binary_name + binary_ext),
        node_bin_dir / (binary_name + node_ext),
        composer_bin_dir / (binary_name + binary_ext),
    ]

    for path in search_paths:
        if path.is_file() and os.access(path, os.X_OK):
            return str(path)

    return shutil.which(binary_name)
