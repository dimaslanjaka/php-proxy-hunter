import glob
import os
import platform
from pathlib import Path
from typing import Optional


def get_system_python_path() -> Optional[str]:
    r"""
    Locate the system-installed Python interpreter path, prioritizing standard locations.

    On Windows, searches C:\ for python.exe, excluding System32. On Linux/macOS, prioritizes /usr/bin/python3, /usr/local/bin/python3, then falls back to searching /usr and /opt.

    Returns:
        Full path to system Python interpreter, or None if not found.
    """
    system = platform.system()
    candidates = []
    if system == "Windows":
        # Search C:\Program Files* for python.exe, exclude System32
        for base in ["C:\\Program Files", "C:\\Program Files (x86)"]:
            for pattern in ["python.exe", "python3.exe"]:
                for py in glob.glob(f"{base}\\**\\{pattern}", recursive=True):
                    if "System32" not in py:
                        candidates.append(py)
    else:
        # Linux/macOS: prioritize system python first
        for path in [
            "/usr/bin/python3",
            "/usr/local/bin/python3",
            "/usr/bin/python",
            "/usr/local/bin/python",
        ]:
            if os.path.isfile(path) and os.access(path, os.X_OK):
                return path
        # Fallback: glob search in /usr and /opt
        for pattern in ["/usr/**/python*", "/opt/**/python*"]:
            for py in glob.glob(pattern, recursive=True):
                if os.path.isfile(py) and os.access(py, os.X_OK):
                    if not (
                        py.startswith("/usr/bin/") or py.startswith("/usr/local/bin/")
                    ):
                        candidates.append(py)
    # Test candidates
    for candidate in candidates:
        try:
            import subprocess

            result = subprocess.run(
                [candidate, "--version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE
            )
            if result.returncode == 0:
                return candidate
        except Exception:
            continue
    return None
