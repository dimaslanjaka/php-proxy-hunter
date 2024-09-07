import base64
import hashlib
import inspect
import json
import os
import random
import re
import subprocess
import sys
from typing import Any, Dict, List, Optional, Tuple, TypeVar, Union

from proxy_hunter import resolve_parent_folder, write_file

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_platform import is_debug

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


def get_relative_path(*args: Union[str, bytes]) -> str:
    """
    Get the relative path from the current working directory (CWD).

    Args:
        *args (Union[str, bytes]): Variable number of path components.

    Returns:
        str: The normalized relative path.
    """
    join_path = str(os.path.join(*args))
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


def debug_log(*args: Any, sep: Optional[str] = " ", end: Optional[str] = "\n") -> None:
    """
    Log debugging information to the console if the current device matches a specific device name.

    Args:
        *args (Any): Debugging information to be logged.
        sep (Optional[str], optional): Separator between arguments. Defaults to ' '.
        end (Optional[str], optional): Ending character. Defaults to '\n'.
    """
    if is_debug():
        message = sep.join(map(str, args)) + end
        # Print to console
        print(message, end="")
        # Write to file
        file_path = get_relative_path("tmp/debug.log")
        resolve_parent_folder(file_path)
        with open(file_path, "a") as file:
            file.write(message)


def get_message_exception(e: Union[Exception, Any, Dict]) -> str:
    """
    Extracts the error message from an exception.

    Args:
        e (Union[Exception, Any, Dict]): The exception object.

    Returns:
        str: The error message extracted from the exception.
    """
    if isinstance(e, Exception) and e.args:
        return str(e.args[0]).strip()
    else:
        return str(e).strip()


def debug_exception(e: Union[Exception, Any, Dict]) -> str:
    """
    Extracts the error message from an exception.

    Args:
        e (Union[Exception, Any, Dict]): The exception object.

    Returns:
        str: The error message extracted from the exception.
    """
    if isinstance(e, Exception):
        trace = []
        tb = e.__traceback__
        while tb is not None:
            trace.append(
                {
                    "filename": tb.tb_frame.f_code.co_filename,
                    "name": tb.tb_frame.f_code.co_name,
                    "lineno": tb.tb_lineno,
                }
            )
            tb = tb.tb_next
        return str(
            {
                "type": type(e).__name__,
                "message": get_message_exception(e),
                "trace": trace,
            }
        )
    return f"Not exception {str(e)}"


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


def is_class_has_parameter(clazz: type, key: str) -> bool:
    """
    Check if a class has a specified parameter in its constructor.

    Args:
        clazz (type): The class to inspect.
        key (str): The parameter name to check for.

    Returns:
        bool: True if the class has the specified parameter, False otherwise.
    """
    inspect_method = inspect.signature(clazz)
    return key in inspect_method.parameters


def get_random_dict(dictionary: Dict) -> Tuple:
    """
    Return a random key-value pair from the given dictionary.

    Parameters:
        dictionary (dict): The dictionary from which to select a random key-value pair.

    Returns:
        tuple: A tuple containing a random key and its corresponding value.
    """
    random_key = random.choice(list(dictionary.keys()))
    random_value = dictionary[random_key]
    return random_key, random_value


def keep_alphanumeric_and_remove_spaces(input_string: str) -> str:
    """
    Removes spaces and keeps only alphanumeric characters from the input string.

    Args:
    - input_string (str): The input string containing alphanumeric and non-alphanumeric characters.

    Returns:
    - str: The cleaned string containing only alphanumeric characters.
    """
    # Remove spaces
    input_string = input_string.replace(" ", "")

    # Keep only alphanumeric characters using regular expression
    input_string = re.sub(r"[^a-zA-Z0-9]", "", input_string)

    return input_string


def get_unique_dicts_by_key_in_list(
    dicts: List[Dict[str, str]], key: str
) -> List[Dict[str, str]]:
    """
    Returns a list of unique dictionaries from the input list of dictionaries based on a specified key.

    Args:
        dicts (List[Dict[str, str]]): The list of dictionaries to process.
        key (str): The key based on which uniqueness is determined.

    Returns:
        List[Dict[str, str]]: A list of unique dictionaries based on the specified key.

    Example:
        ```
        proxies: List[Dict[str, str]] = [{'proxy': 'proxy1'}, {'proxy': 'proxy2'}, {'proxy': 'proxy1'}, {'proxy': 'proxy3'}]
        unique_proxies = get_unique_dicts_by_key_in_list(proxies, 'proxy')
        print(unique_proxies)
        ```
    """
    unique_values = set()
    unique_dicts = []

    for d in dicts:
        value = d.get(key)
        if value not in unique_values:
            unique_values.add(value)
            unique_dicts.append(d)

    return unique_dicts


def move_string_between(
    source_file_path: str,
    destination_file_path: str,
    string_to_remove: Optional[Union[str, List[str]]] = None,
) -> bool:
    """
    Move specified strings from the source file to the destination file and remove them from the source file.

    Parameters:
    - source_file_path: Path to the source file.
    - destination_file_path: Path to the destination file.
    - string_to_remove: String or list of strings to remove from the source file.

    Returns:
    - True if operation is successful, False otherwise.
    """
    if not source_file_path or not destination_file_path:
        return False

    if not os.path.exists(destination_file_path):
        write_file(destination_file_path, "")

    if not os.path.exists(source_file_path):
        write_file(source_file_path, "")

    try:
        # Read content from the source file
        with open(source_file_path, "r", encoding="utf-8") as source:
            source_content = source.read()

        if not string_to_remove:
            return False

        # Ensure string_to_remove is a list
        if isinstance(string_to_remove, str):
            string_to_remove = [string_to_remove]

        # Check and remove each string in the list
        for string in string_to_remove:
            if string in source_content:
                source_content = source_content.replace(string, "")

                # Append the removed string to the destination file
                with open(destination_file_path, "a", encoding="utf-8") as destination:
                    destination.write("\n" + string + "\n")

        # Write the modified content back to the source file
        with open(source_file_path, "w", encoding="utf-8") as source:
            source.write(source_content)

        return True

    except FileNotFoundError as e:
        print(f"File not found: {e}")
        return False
    except Exception as e:
        print(f"An error occurred: {e}")
        return False


T = TypeVar("T")


def get_random_item_list(arr: List[T]) -> T:
    random.shuffle(arr)
    return random.choice(arr)


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


def md5(input_string: str) -> str:
    return hashlib.md5(input_string.encode()).hexdigest()


def clean_dict(d: Dict[str, Any]) -> Dict[str, Any]:
    """
    Remove keys from the dictionary where the value is empty (None or an empty string)
    or under zero (for numerical values).

    Args:
        d (Dict[str, Any]): The dictionary to be cleaned.

    Returns:
        Dict[str, Any]: A new dictionary with unwanted key-value pairs removed.
    """
    return {
        k: v
        for k, v in d.items()
        if (v not in [None, "", 0] and (isinstance(v, (int, float)) and v >= 0))
    }


def base64_encode(data: Union[str, bytes]) -> str:
    """
    Encodes a given string or bytes into Base64.

    Args:
        data (Union[str, bytes]): The data to encode. Can be a string or bytes.

    Returns:
        str: The Base64 encoded string.
    """
    if isinstance(data, str):
        data = data.encode("utf-8")  # Convert string to bytes if necessary
    return base64.b64encode(data).decode("utf-8")


def base64_decode(encoded_data: str) -> str:
    """
    Decodes a Base64 encoded string back to its original string.

    Args:
        encoded_data (str): The Base64 encoded string to decode.

    Returns:
        str: The decoded string.
    """
    decoded_bytes = base64.b64decode(encoded_data)
    return decoded_bytes.decode("utf-8")  # Assuming the original data was UTF-8 encoded


def unique_non_empty_strings(strings: Optional[List[Union[str, None]]]) -> List[str]:
    """
    Filter out non-string elements, empty strings, and None from the input list,
    and return a list of unique non-empty strings.

    Args:
        strings (List[Union[str, None]]): The list of strings to process.

    Returns:
        List[str]: A list of unique non-empty strings.
    """
    if not strings:
        return []
    unique_strings = set()
    for s in strings:
        if isinstance(s, str) and s not in ("", None):
            unique_strings.add(s)
    return list(unique_strings)


def split_list_into_chunks(
    lst: List[int], chunk_size: Optional[int] = None, total_chunks: Optional[int] = None
) -> List[List[int]]:
    """
    Split a list into chunks either by a specified chunk size or into a specified number of chunks.

    Args:
        lst (List[int]): The list to be split into chunks.
        chunk_size (Optional[int]): The size of each chunk. If provided, the list is split into chunks of this size.
        total_chunks (Optional[int]): The number of chunks to split the list into. If provided, the list is split into this many chunks.

    Returns:
        List[List[int]]: A list of lists, where each inner list is a chunk of the original list.

    Raises:
        ValueError: If neither `chunk_size` nor `total_chunks` is provided.
    """
    if chunk_size is not None:
        # Split by specific chunk size
        return [lst[i : i + chunk_size] for i in range(0, len(lst), chunk_size)]

    elif total_chunks is not None:
        # Split into a specific number of chunks
        chunk_size = len(lst) // total_chunks
        remainder = len(lst) % total_chunks
        chunks = []
        start = 0

        for i in range(total_chunks):
            end = start + chunk_size + (1 if i < remainder else 0)
            chunks.append(lst[start:end])
            start = end

        return chunks

    else:
        raise ValueError("Either chunk_size or total_chunks must be provided.")
