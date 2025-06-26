import os
import platform
import shutil
import stat
import subprocess
import sys
from typing import List


def run_command(command: List[str]):
    try:
        result = subprocess.run(
            command, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE
        )
        return result.stdout.decode("utf-8").strip()
    except subprocess.CalledProcessError:
        return None


def is_git_repo_and_on_branch(branch_name: str = "python"):
    return (
        run_command(["git", "rev-parse", "--is-inside-work-tree"]) is not None
        and run_command(["git", "branch", "--show-current"]) == branch_name
    )


def check_package(package_name: str):
    try:
        __import__(package_name)
        return True
    except ImportError:
        return False


def delete_path(path: str) -> None:
    if os.path.exists(path):
        try:
            if os.path.isdir(path):
                shutil.rmtree(path, ignore_errors=True)
            elif os.path.isfile(path):
                os.remove(path)
            print(f"'{path}' deleted successfully.")
        except OSError as e:
            print(f"Error deleting '{path}': {e}")
    else:
        print(f"Path '{path}' does not exist.")


def ensure_directories(directories: List[str]):
    current_directory = os.path.dirname(os.path.abspath(__file__))
    for dir_path in directories:
        full_path = os.path.join(current_directory, dir_path)
        os.makedirs(full_path, exist_ok=True)
        os.chmod(full_path, stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)


# List of packages to check
packages = ["pycountry", "django", "requests", "pytz", "timezonefinder"]

# Add Windows-specific packages if needed
if platform.system() == "Windows" and is_git_repo_and_on_branch("python"):
    packages.extend(["PySide6", "psutil", "wmi", "qtawesome", "nuitka"])

# Check for missing packages
missing_packages = [pkg for pkg in packages if not check_package(pkg)]
if missing_packages:
    print(f"Missing packages: {', '.join(missing_packages)}")
    # Generate requirements.txt
    subprocess.check_call([sys.executable, "requirements_install.py", "--generate"])
    # Install missing packages
    subprocess.check_call(
        [sys.executable, "-m", "pip", "install", "-r", "requirements.txt"]
    )
else:
    print("All required packages are installed.")

# Define directories
temp_dirs = ["tmp/runners", "tmp/logs"]
dirs = [
    ".cache",
    "config",
    "assets/proxies",
    "assets/chrome-profiles",
    "assets/chrome",
    "tmp/cookies",
    "tmp/data",
    "tmp/logs",
    "dist",
] + temp_dirs

# Clean temporary directories and recreate all necessary directories
for temp_dir in temp_dirs:
    delete_path(temp_dir)

ensure_directories(dirs)
