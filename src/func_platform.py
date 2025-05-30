import importlib
import os
import platform
import socket
import sys

import dotenv

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path

# Load environment variables from .env
dotenv_file = get_relative_path(".env")
if os.path.isfile(dotenv_file):
    dotenv.load_dotenv(dotenv_file)


def import_windows_packages():
    try:
        global win32api
        global wmi
        win32api = importlib.import_module("win32api")
        wmi = importlib.import_module("wmi")
        print("Windows-specific packages imported successfully.")
    except ImportError as e:
        print(f"Failed to import Windows-specific packages: {e}")


def import_linux_packages():
    print("No specific packages to import for Linux.")
    # Your Linux-specific code here


def is_debug() -> bool:
    """
    Check current device is debug or not
    """
    is_github_ci = os.getenv("CI") is not None and os.getenv("GITHUB_ACTIONS") == "true"
    is_github_codespaces = os.getenv("CODESPACES") == "true"

    if is_github_ci or is_github_codespaces:
        return True

    # My device lists
    debug_pc = os.getenv("DEBUG_DEVICES", "").split(",")
    hostname = socket.gethostname()

    if hostname.startswith("codespaces-"):
        return True

    return hostname in debug_pc


def is_django_environment():
    return "DJANGO_SETTINGS_MODULE" in os.environ


def main():
    if platform.system() == "Windows":
        import_windows_packages()
        # Now you can use wmi and win32api functions here
        # Example usage of wmi
        c = wmi.WMI()
        for os in c.Win32_OperatingSystem():
            print(os.Caption, os.OSArchitecture)
    else:
        import_linux_packages()


def get_pc_name():
    return socket.gethostname()


if __name__ == "__main__":
    main()
