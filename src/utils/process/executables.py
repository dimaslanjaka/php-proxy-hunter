import json
import os
import platform
import shlex
import shutil
import subprocess
import sys
from pathlib import Path


_MODULE_DIR = Path(__file__).resolve().parent


def _is_windows():
    return platform.system().lower().startswith("windows")


def _ensure_executables_json():
    out_file = _MODULE_DIR / "executables.json"
    if out_file.exists():
        return out_file

    # Attempt to generate the JSON by probing common locations and PATH
    root = _MODULE_DIR.resolve().parents[2]

    def candidates(paths):
        out = []
        for p in paths:
            if "{root}" in p:
                out.append(p.replace("{root}", str(root)))
            else:
                out.append(p)
        return out

    php_paths = candidates(
        [
            shutil.which("php") or "",
            "/usr/bin/php",
            "/usr/local/bin/php",
            str(root / "venv" / "bin" / "php"),
            str(root / ".venv" / "bin" / "php"),
            r"C:\\php\\php.exe",
            r"C:\\Program Files\\PHP\\php.exe",
            r"C:\\xampp\\php\\php.exe",
        ]
    )

    python_paths = candidates(
        [
            sys.executable or "",
            shutil.which("python3") or "",
            shutil.which("python") or "",
            "/usr/bin/python3",
            "/usr/local/bin/python3",
            str(root / "venv" / "bin" / "python"),
            str(root / ".venv" / "bin" / "python"),
            r"C:\\Python39\\python.exe",
            r"C:\\Program Files\\Python39\\python.exe",
        ]
    )

    def is_exe(p):
        if not p:
            return False
        p = str(p)
        if _is_windows():
            return os.path.exists(p)
        return os.path.exists(p) and os.access(p, os.X_OK)

    found_php = None
    for p in php_paths:
        if is_exe(p):
            found_php = p
            break
    if not found_php:
        try:
            which_cmd = ["where", "php"] if _is_windows() else ["which", "php"]
            out = subprocess.check_output(
                which_cmd, stderr=subprocess.STDOUT, text=True
            )
            first = out.strip().splitlines()[0]
            if first:
                found_php = first
        except Exception:
            found_php = None

    found_python = None
    for p in python_paths:
        if is_exe(p):
            found_python = p
            break
    if not found_python:
        try:
            which_cmd = ["where", "python"] if _is_windows() else ["which", "python3"]
            out = subprocess.check_output(
                which_cmd, stderr=subprocess.STDOUT, text=True
            )
            first = out.strip().splitlines()[0]
            if first:
                found_python = first
        except Exception:
            found_python = None

    result = {"php": found_php or None, "python": found_python or None}
    try:
        out_file.write_text(json.dumps(result, indent=2, ensure_ascii=False))
    except Exception:
        # best effort; ignore write failures
        pass

    return out_file


def _read_executables_json():
    p = _ensure_executables_json()
    try:
        data = json.loads(p.read_text())
    except Exception:
        data = {}
    return data


def _quote_for_windows(path: str) -> str:
    return '"' + path.replace('"', '\\"') + '"'


def getPhpExecutable(escape: bool = False):
    """Return configured PHP executable path or None.

    If `escape` is True, returns a shell-safe representation: on Windows
    the path is double-quoted; on POSIX platforms the path is shell-quoted.
    """
    data = _read_executables_json()
    php_path = data.get("php")
    if escape and php_path:
        if _is_windows():
            return _quote_for_windows(php_path)
        return shlex.quote(php_path)
    return php_path


def getPythonExecutable(escape: bool = False):
    """Return configured Python executable path or None.

    If `escape` is True, returns a shell-safe representation: on Windows
    the path is double-quoted; on POSIX platforms the path is shell-quoted.
    """
    data = _read_executables_json()
    python_path = data.get("python")
    if escape and python_path:
        if _is_windows():
            return _quote_for_windows(python_path)
        return shlex.quote(python_path)
    return python_path


__all__ = [
    "getPhpExecutable",
    "getPythonExecutable",
]
