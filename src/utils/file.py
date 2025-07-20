import os
import platform
import shutil
import sys
import glob
from pathlib import Path
from typing import Optional, Union


def get_binary_path(
    binary_name: str, base_dir: Optional[Union[Path, str]] = None
) -> Optional[str]:
    """
    Locate a binary by name, prioritizing project-local environments.

    Search order:
      1. Python venv (bin/Scripts)
      2. node_modules/.bin
      3. vendor/bin (PHP Composer)
      4. System PATH

    Args:
        binary_name: Name of the binary (e.g., 'python', 'eslint', 'phpcs')
        base_dir: Base directory for local search. Can be a Path, str, or None. Defaults to current working directory.

    Returns:
        Full path to binary, or None if not found.
    """
    if base_dir is not None:
        base_dir = Path(base_dir).resolve()
    else:
        base_dir = Path.cwd().resolve()
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


def get_system_python_path() -> Optional[str]:
    r"""
    Locate the system-installed Python interpreter path, prioritizing standard locations.

    On Windows, searches C:\\ for python.exe, excluding System32. On Linux/macOS, prioritizes /usr/bin/python3, /usr/local/bin/python3, then falls back to searching /usr and /opt.

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


if __name__ == "__main__":
    localPython = get_binary_path("python")
    print(
        f"Local Python binary: {localPython}"
        if localPython
        else "Local Python binary not found."
    )
    systemPython = get_system_python_path()
    print(
        f"System Python interpreter: {systemPython}"
        if systemPython
        else "System Python interpreter not found."
    )
