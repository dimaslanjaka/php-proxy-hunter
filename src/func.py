import json
import os
import random
import subprocess
import sys
from typing import List, Optional, Union

from proxy_hunter import write_file

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

# set Timezone
os.environ["TZ"] = "Asia/Jakarta"

# determine if application is a script file or frozen exe
if getattr(sys, "frozen", False):
    __CWD__ = os.path.dirname(os.path.realpath(sys.executable))
elif __file__:
    __CWD__ = os.getcwd()


def is_nuitka() -> bool:
    """
    Check if the script is compiled with Nuitka.
    """
    is_nuitka = "__compiled__" in globals()
    is_nuitka2 = "NUITKA_ONEFILE_PARENT" in os.environ
    return is_nuitka or is_nuitka2


is_nuitka_standalone = "__compiled__" in globals()
is_nuitka_onefile = "NUITKA_ONEFILE_PARENT" in os.environ


def get_nuitka_file(file_path: str) -> str:
    """
    Get the path for a file within a Nuitka compiled application.

    Args:
        file_path (str): The file path.

    Returns:
        str: The absolute file path.
    """
    # Get the directory of the current script (func.py)
    script_dir = os.path.dirname(__file__)
    # Go up one directory level to the root of the project
    root_dir = os.path.dirname(script_dir)
    return os.path.join(root_dir, file_path)


def get_relative_path(*args: str) -> str:
    """
    Get the relative path from the current working directory (CWD).

    Args:
        *args (Union[str, bytes]): Variable number of path components.

    Returns:
        str: The normalized relative path.
    """
    join_path = os.path.join(*args)
    result = os.path.normpath(str(os.path.join(__CWD__, join_path)))
    if is_nuitka():
        result = os.path.normpath(
            str(os.path.join(os.path.dirname(sys.argv[0]), join_path))
        )
        # debug_log(os.path.dirname(sys.argv[0]), os.path.join(*args))
    return result


def resolve_relative_path(data: Optional[str] = None, *args: Union[str, bytes]) -> str:
    """
    Get relative path and optionally create if it does not exist.

    Args:
        data (Optional[str]): Optional data to write if the file does not exist.
        *args (Union[str, bytes]): Variable number of path components.

    Returns:
        str: The normalized relative path.
    """
    relative = get_relative_path(*args)

    if not os.path.exists(relative):
        if not data:
            data = ""  # Default empty data if not provided
        if relative.endswith(".json"):
            data = "{}"  # Default empty JSON object if it's a .json file

        # Write the file with the provided or default data
        write_file(relative, data)

    return relative


# debug_log(f"PC name: {get_pc_name()}")
# debug_log(f"current CWD {__CWD__}")

# https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json
# https://windows.php.net/downloads/releases/archives/php-7.4.3-nts-Win32-vc15-x86.zip


def run_php_script(php_script_path: str, *args: str) -> None:
    """
    Run a PHP script with optional arguments.

    Args:
        php_script_path (str): Path to the PHP script.
        *args (str): Optional arguments to be passed to the PHP script.

    Returns:
        None
    """
    php_executable = "php"

    # Construct the command to execute the PHP script
    command: List[str] = [php_executable, php_script_path]
    command.extend(args)

    # Run the PHP script
    subprocess.run(command)


def get_random_http_profile(json_file):
    """
    Get a random proxy and useragent from a JSON file.

    Args:
        json_file (str): Path to the JSON file containing proxy data.

    Returns:
        dict: A dictionary containing a random proxy and useragent.
    """
    with open(json_file, "r", encoding="utf-8") as file:
        data = json.load(file)

    # Filter profiles where 'type' is 'http'
    http_profiles = [profile for profile in data if profile["type"].lower() == "http"]
    # profile for profile in data if 'http' in profile['type'].lower()]

    # Check if there are any http_profiles
    if not http_profiles:
        raise ValueError("No HTTP profiles found in the JSON data.")

    # Get a random index within the range of http_profiles list
    index = random.randint(0, len(http_profiles) - 1)

    # Return a random http profile
    return http_profiles[index]


def get_random_profile(json_file):
    """
    Get a random proxy and useragent from a JSON file.

    Args:
        json_file (str): Path to the JSON file containing proxy data.

    Returns:
        dict: A dictionary containing a random proxy and useragent.
    """
    with open(json_file, "r", encoding="utf-8") as file:
        data = json.load(file)

    # Get a random index within the range of the list
    index = random.randint(0, len(data) - 1)
    return data[index]  # data[random_index]['useragent']
