from pathlib import Path
from typing import Optional, Union

from .get_binary_path import get_binary_path


def get_python_path(cwd: Optional[Union[str, Path]] = None) -> Optional[str]:
    """
    Locate the Python interpreter path, prioritizing project-local environments.

    Args:
        base_dir: Base directory for local search. Defaults to current working directory.

    Returns:
        Full path to Python interpreter, or None if not found.
    """
    if not cwd:
        cwd = Path.cwd()
    elif isinstance(cwd, str):
        cwd = Path(cwd).resolve()
    binaries = [
        get_binary_path("python3", cwd),
        get_binary_path("python", cwd),
    ]
    for binary in binaries:
        if binary and Path(binary).is_file():
            return binary
    return None
