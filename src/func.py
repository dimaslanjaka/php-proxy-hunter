import hashlib
import inspect
import json
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
import random
import re
import shutil
import socket
import string
import subprocess
import sys
import tempfile
import time
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional, Tuple, TypeVar, Union

# set Timezone
os.environ['TZ'] = 'Asia/Jakarta'

# determine if application is a script file or frozen exe
if getattr(sys, 'frozen', False):
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
        result = os.path.normpath(str(os.path.join(os.path.dirname(sys.argv[0]), join_path)))
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
            data = ''  # Default empty data if not provided
        if relative.endswith('.json'):
            data = '{}'  # Default empty JSON object if it's a .json file

        # Write the file with the provided or default data
        write_file(relative, data)

    return relative


def get_pc_name():
    return socket.gethostname()


def is_debug():
    return get_pc_name() == "DESKTOP-JVTSJ6I"


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
            trace.append({
                "filename": tb.tb_frame.f_code.co_filename,
                "name": tb.tb_frame.f_code.co_name,
                "lineno": tb.tb_lineno
            })
            tb = tb.tb_next
        return str({
            'type': type(e).__name__,
            'message': get_message_exception(e),
            'trace': trace
        })
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
    php_executable = 'php'

    # Construct the command to execute the PHP script
    command: List[str] = [php_executable, php_script_path]
    command.extend(args)

    # Run the PHP script
    subprocess.run(command)


def serialize(obj):
    """Serialize an object to a dictionary."""
    if hasattr(obj, '__dict__'):
        return obj.__dict__
    elif isinstance(obj, (int, float, str, bool, type(None))):
        return obj
    else:
        raise TypeError(f"Object of type {type(obj)} is not JSON serializable")


def write_json(filePath: str, data: Any):
    """
    write json file
    """
    if not data:
        return
    with open(filePath, 'w', encoding='utf-8') as file:
        json.dump(data, file, indent=2, ensure_ascii=False, default=serialize)


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


def copy_file(source_file: str, destination_file: str) -> None:
    """
    Copy a file from source to destination, overwriting if the destination file exists.

    Parameters:
    - source_file (str): Path to the source file.
    - destination_file (str): Path to the destination file.

    Returns:
    - None
    """
    try:
        # Copy file, overwriting destination if it exists
        shutil.copyfile(source_file, destination_file)
        print(f"File '{source_file}' copied to '{destination_file}'")
    except FileNotFoundError:
        print(f"Error: File '{source_file}' not found.")
    except Exception as e:
        print(f"Error: {e}")


def copy_folder(source_folder: str, destination_folder: str, overwrite: Optional[bool] = False) -> None:
    """
    Copy a folder and its contents recursively from the source location to the destination location.

    Args:
        source_folder (str): The path to the source folder to be copied.
        destination_folder (str): The path to the destination folder where the source folder will be copied.
        overwrite (bool, optional): If True, overwrite the destination folder if it already exists.
                                    Defaults to False.

    Raises:
        FileExistsError: If the destination folder already exists and overwrite is False.

    Returns:
        None
    """
    # Ensure destination parent folder exists
    os.makedirs(os.path.dirname(destination_folder), exist_ok=True)

    if overwrite:
        if shutil.os.path.exists(destination_folder):
            shutil.rmtree(destination_folder)
    shutil.copytree(source_folder, destination_folder)


def file_move_lines(source_file: str, destination_file: str, n: int) -> None:
    """
    Move the first n lines from the source file to the destination file,
    then remove those lines from the source file.

    Args:
    - source_file (str): Path to the source file.
    - destination_file (str): Path to the destination file.
    - n (int): Number of lines to move.

    Returns:
    - None
    """
    with open(source_file, 'r+', encoding='utf-8') as source:
        lines = source.readlines()

    with open(destination_file, 'a+', encoding='utf-8') as destination:
        destination.writelines(lines[:n])

    with open(source_file, 'w+', encoding='utf-8') as source:
        source.writelines(lines[n:])


def list_files_in_directory(directory: str) -> List[str]:
    """
    List all files in the given directory and return a list of their absolute paths.

    Args:
        directory (str): The directory to list files from.

    Returns:
        List[str]: A list of absolute paths to the files in the directory.
    """
    if not os.path.exists(directory):
        return []

    file_paths = []
    for root, _, files in os.walk(directory):
        for file in files:
            file_paths.append(os.path.abspath(os.path.join(root, file)))

    return file_paths


def count_lines_in_file(file_path: str) -> int:
    """
    Count the number of lines in a given file.

    Args:
        file_path (str): The path to the file to be counted.

    Returns:
        int: The total number of lines in the file.
    """
    # Open the file in read mode
    with open(file_path, 'r', encoding='utf-8') as file:
        # Use a loop to iterate through each line and count them
        line_count = sum(1 for line in file)

    return line_count


def is_directory_created_days_ago_or_more(directory_path: str, days: int) -> bool:
    """
    Check if the directory exists and if it was created 'days' days ago or more.

    Args:
        directory_path (str): The path to the directory.
        days (int): Number of days ago to check against.

    Returns:
        bool: True if the directory exists and was created 'days' days ago or more, False otherwise.
    """
    # Check if the directory exists
    if os.path.exists(directory_path):
        # Get the modification time of the directory
        mod_time = os.path.getmtime(directory_path)

        # Get current time
        current_time = time.time()

        # Calculate the time difference
        time_diff = current_time - mod_time

        # Define 'days' days in seconds
        days_seconds = days * 24 * 60 * 60

        # Check if the directory was created 'days' days ago or more
        if time_diff >= days_seconds:
            return True
        else:
            return False
    else:
        return False


def is_matching_regex(pattern: str, text: str):
    """
    Check if the given text matches the provided regular expression pattern.

    Args:
    pattern (str): The regular expression pattern to match against.
    text (str): The text to check for a match.

    Returns:
    bool: True if the text matches the pattern, False otherwise.
    """
    # return bool(re.match(pattern, text))
    return re.search(pattern, text, re.MULTILINE) is not None


def remove_trailing_hyphens(string: Optional[str]) -> str:
    if not string:
        return ''
    if string == '-' or not string.strip():
        return ''
    return re.sub(r'-+$', '', string)


def get_random_folder(directory: str) -> str:
    """
    Return a randomly selected folder path inside the specified directory.

    Args:
        directory (str): The path to the directory.

    Returns:
        str: The full path of the randomly selected folder.

    Raises:
        ValueError: If the specified directory does not exist or if there are no subdirectories in it.
    """
    if not os.path.isdir(directory):
        raise ValueError("The specified directory does not exist.")

    folders: List[str] = [os.path.join(directory, folder) for folder in os.listdir(directory) if
                          os.path.isdir(os.path.join(directory, folder))]

    if not folders:
        raise ValueError("There are no subdirectories in the specified directory.")

    return os.path.normpath(random.choice(folders))


def find_substring_from_regex(pattern: str, string: str) -> Optional[str]:
    """
    Find and return the first substring in the given string that matches the specified regex pattern.

    Args:
        pattern (str): The regex pattern to search for.
        string (str): The string to search in.

    Returns:
        str: The matched substring, or None if no match is found.
    """
    matches = re.finditer(pattern, string, re.MULTILINE)
    for match in matches:
        return match.group()
    return None


def delete_path_if_exists(path):
    """
    Delete a file or folder if it exists.

    Parameters:
        path (str): The path to the file or folder to be deleted.

    Returns:
        None

    Raises:
        None
    """
    try:
        if os.path.exists(path):
            if os.path.isfile(path):
                os.remove(path)
                print(f"File '{path}' deleted.")
            elif os.path.isdir(path):
                shutil.rmtree(path)
                print(f"Folder '{path}' and its contents deleted.")
        else:
            print(f"'{path}' does not exist.")
    except PermissionError as e:
        print(f"Permission error: {e}")


def read_file(file_path: str) -> Optional[str]:
    """
    Read content from a file.

    Args:
        file_path (str): The path to the file to read.

    Returns:
        Optional[str]: The content of the file if successful, None otherwise.
    """
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            content = file.read()
        return content
    except FileNotFoundError:
        print(f"Error: File '{file_path}' not found.")
        return None
    except Exception as e:
        print(f"Error: An exception occurred - {e}")
        return None


def write_file(file_path: str, content: str) -> None:
    """
    Write content to a file.

    Args:
        file_path (str): The path to the file to write.
        content (str): The content to write to the file.
    """
    try:
        resolve_parent_folder(file_path)
        with open(file_path, 'w', encoding='utf-8') as file:
            file.write(content)
        # print(f"File '{file_path}' has been successfully written.")
    except Exception as e:
        print(f"Error: An exception occurred - {e}")


def file_append_str(filename: str, string_to_add: str) -> None:
    """
    Append a string to a file.

    Args:
        filename (str): The path to the file.
        string_to_add (str): The string to append to the file.

    Returns:
        None
    """
    try:
        with open(filename, 'a+', encoding='utf-8') as file:
            # file.write(string_to_add.encode('utf-8').decode('utf-8', 'ignore'))
            file.write(f"{string_to_add}\n")
    except Exception as e:
        print(f"Fail append new line {filename} {e.args[0]}")
        pass


def resolve_parent_folder(path: str) -> str:
    """
    Resolves the parent folder of the given path and creates it if it doesn't exist.

    Args:
        path (str): The path string.

    Returns:
        str: The parent folder of the given path.
    """
    parent_folder = os.path.dirname(path)
    if not os.path.exists(parent_folder):
        os.makedirs(parent_folder)
    return parent_folder


def delete_path(path: str) -> None:
    """
    Delete a folder or file specified by the path if it exists.

    Args:
        path (str): The path of the folder or file to delete.
    """
    if not os.path.exists(path):
        print(f"Path '{path}' does not exist.")
        return

    try:
        if os.path.isdir(path):
            shutil.rmtree(path, ignore_errors=True)
            print(f"Folder '{path}' and its contents deleted successfully.")
        elif os.path.isfile(path):
            os.remove(path)
            print(f"File '{path}' deleted successfully.")
        else:
            print(f"Path '{path}' is neither a file nor a folder.")
    except OSError as e:
        print(f"Error deleting '{path}': {e}")


def sanitize_filename(filename):
    """
    Sanitize a filename by removing any character that is not alphanumeric, underscore, dash, or period.

    Args:
        filename (str or None): The filename to sanitize.

    Returns:
        str: The sanitized filename.
    """
    if not filename:
        filename = ''

    # Remove any character that is not alphanumeric, underscore, dash, or period
    filename = re.sub(r"[^a-zA-Z0-9_-]+", '-', filename)
    filename = re.sub(r"-+", '-', filename)

    return filename


def remove_string_from_file(file_path: str, strings_to_remove: Union[str, List[str]]) -> None:
    """
    Removes all occurrences of specified strings from a file.

    Args:
        file_path (str): The path to the file.
        strings_to_remove (Union[str, List[str]]): The string or list of strings to be removed from the file.

    Returns:
        None
    """
    if not os.path.exists(file_path):
        return

    # Ensure strings_to_remove is a list
    if isinstance(strings_to_remove, str):
        strings_to_remove = [strings_to_remove]

    # Escape strings and create regex pattern
    escaped_strings = [re.escape(s) for s in strings_to_remove[:1000]]
    pattern = "|".join(escaped_strings)
    regex = re.compile(pattern)

    # Create a temporary file
    random_string = ''.join(random.choice(string.ascii_letters) for _ in range(5))
    temp_file = get_relative_path(f'tmp/runners/{random_string}.txt')
    if not os.path.exists(temp_file):
        write_file(temp_file, '')

    # Read the original file and write to the temporary file with the strings removed
    with open(file_path, 'r', encoding='utf-8') as file, temp_file:
        for line in file:
            # Replace all occurrences of the pattern with an empty string
            modified_line = regex.sub("", line)
            temp_file.write(modified_line)

    # Replace the original file with the temporary file
    shutil.move(temp_file.name, file_path)


def keep_alphanumeric_and_remove_spaces(input_string: str) -> str:
    """
    Removes spaces and keeps only alphanumeric characters from the input string.

    Args:
    - input_string (str): The input string containing alphanumeric and non-alphanumeric characters.

    Returns:
    - str: The cleaned string containing only alphanumeric characters.
    """
    # Remove spaces
    input_string = input_string.replace(' ', '')

    # Keep only alphanumeric characters using regular expression
    input_string = re.sub(r'[^a-zA-Z0-9]', '', input_string)

    return input_string


def remove_duplicate_line_from_file(filename: str) -> None:
    """
    Removes duplicated lines from a file and overwrites the original file.

    Args:
        filename (str): The name of the file to clean.

    Returns:
        None
    """
    if not os.path.exists(filename):
        return
    # Copy content to a temporary file
    with open(filename, 'r', encoding='utf-8') as original_file, tempfile.NamedTemporaryFile(mode='w', encoding='utf-8', delete=False) as temp_file:
        lines_seen = set()  # Set to store unique lines
        for line in original_file:
            if line not in lines_seen:
                temp_file.write(line)
                lines_seen.add(line)

    # Replace original file with cleaned content
    temp_filename = temp_file.name
    import shutil
    shutil.move(temp_filename, filename)


def get_unique_dicts_by_key_in_list(dicts: List[Dict[str, str]], key: str) -> List[Dict[str, str]]:
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


def remove_string_and_move_to_file(source_file_path, destination_file_path, string_to_remove):
    if not source_file_path or not destination_file_path or not string_to_remove:
        return False
    if not os.path.exists(destination_file_path):
        write_file(destination_file_path, '')
    if not os.path.exists(source_file_path):
        write_file(source_file_path, '')
    try:
        # Read content from the source file
        with open(source_file_path, 'r', encoding='utf-8') as source:
            source_content = source.read()

        # Check if the string to remove exists in the source content
        if string_to_remove not in source_content:
            return False

        # Remove the desired string
        modified_content = source_content.replace(string_to_remove, '')

        # Write the modified content back to the source file
        with open(source_file_path, 'w', encoding='utf-8') as source:
            source.write(modified_content)

        # Append the removed string to the destination file
        with open(destination_file_path, 'a', encoding='utf-8') as destination:
            destination.write('\n' + string_to_remove + '\n')

        return True
    except FileNotFoundError as e:
        print(str(e))
        return False
    except Exception as e:
        print("An error occurred:", str(e))
        return False


def is_date_rfc3339_hour_more_than(date_string: Optional[str], hours: int) -> Optional[bool]:
    """
    Check if the given date string is more than specified hours ago.

    Args:
    - date_string (str): The date string in RFC3339 format (e.g., "2024-05-06T12:34:56+00:00").
    - hours (int): The number of hours.

    Returns:
    - bool: True if the date is more than the specified hours ago, False otherwise.
    """
    if not date_string:
        return None
    try:
        # Parse the input date string into a datetime object
        date_time = datetime.fromisoformat(date_string).replace(tzinfo=timezone.utc)

        # Calculate the current time in UTC
        current_time = datetime.now(timezone.utc)

        # Calculate the time difference
        time_difference = current_time - date_time

        # Convert hours to timedelta object
        hours_delta = timedelta(hours=hours)

        # Compare the time difference with the specified hours
        return time_difference >= hours_delta

    except ValueError:
        # Handle invalid date string format
        raise ValueError("Invalid date string format. Please provide a date string in RFC3339 format.")


def size_of_list_in_mb(list_of_strings: List[str]) -> float:
    """
    Calculate the size of a list of strings in megabytes.

    Args:
    - list_of_strings (List[str]): The list of strings to calculate the size of.

    Returns:
    - float: The size of the list in megabytes.
    """
    total_size = sum(sys.getsizeof(string) for string in list_of_strings)
    size_in_mb = total_size / (1024 * 1024)
    return size_in_mb


def file_remove_empty_lines(file_path: str) -> None:
    """
    Remove empty lines from a file.

    Args:
        file_path (str): The path to the input file.

    Returns:
        None
    """
    temp_file = get_relative_path('tmp', md5(file_path) + ".tmp")
    try:
        with open(file_path, 'r', encoding='utf-8') as f_in, open(temp_file, 'w', encoding='utf-8') as f_out:
            for line in f_in:
                if line.strip():  # Check if the line is not empty
                    f_out.write(line)
        # Replace the original file with the temporary file
        shutil.move(temp_file, file_path)
        # print("Empty lines removed from", file_path)
    except Exception:
        # print("File not found.")
        pass


def is_file_larger_than_kb(file_path, size_in_kb=5):
    # Get the size of the file in bytes
    file_size_bytes = os.path.getsize(file_path)

    # Convert bytes to kilobytes
    file_size_kb = file_size_bytes / 1024

    # Check if file size is greater than specified size
    return file_size_kb > size_in_kb


def truncate_file_content(file_path, max_length=0):
    try:
        with open(file_path, 'r+', encoding='utf-8') as file:
            content = file.read()
            if len(content) > max_length:
                file.seek(0)
                file.truncate(max_length)
                file.write(content[:max_length])
                # print(f"Content truncated to {max_length} characters.")
            else:
                pass
    except Exception:
        pass


def sanitize_filename(filename):
    # Define a regular expression to match invalid characters in filenames
    # Add any other characters you want to disallow
    invalid_chars = r'[\\/:\*\?"<>\|]'

    # Replace invalid characters with underscores
    sanitized_filename = re.sub(invalid_chars, '-', filename)
    return remove_trailing_hyphens(sanitized_filename)


def md5(input_string):
    md5_hash = hashlib.md5(input_string.encode()).hexdigest()
    return md5_hash


T = TypeVar('T')


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
    with open(json_file, 'r', encoding='utf-8') as file:
        data = json.load(file)

    # Filter profiles where 'type' is 'http'
    http_profiles = [
        profile for profile in data if profile['type'].lower() == 'http']
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
    with open(json_file, 'r', encoding='utf-8') as file:
        data = json.load(file)

    # Get a random index within the range of the list
    index = random.randint(0, len(data) - 1)
    return data[index]  # data[random_index]['useragent']


def md5(input_string: str) -> str:
    return hashlib.md5(input_string.encode()).hexdigest()
