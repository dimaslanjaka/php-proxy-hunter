from pathlib import Path
from typing import Optional


def realpath(path: str) -> Optional[str]:
    """
    Resolve a filesystem path to an absolute, normalized path.

    This behaves similarly to PHP's `realpath()`:
    - Returns the absolute resolved path if it exists.
    - Resolves symlinks, "." and ".." components.
    - Returns None if the path does not exist.

    Parameters
    ----------
    path : str
        The input filesystem path to resolve.

    Returns
    -------
    Optional[str]
        The resolved absolute path, or None if the path does not exist.
    """
    try:
        return str(Path(path).resolve(strict=True))
    except FileNotFoundError:
        return None
