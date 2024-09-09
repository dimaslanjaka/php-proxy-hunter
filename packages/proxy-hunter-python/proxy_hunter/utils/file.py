import hashlib
import json
import os
import pickle
import random
import re
import shutil
import stat
import string
import sys
import tempfile
import time
from typing import Any, Dict, List, Optional, Tuple, Union

from filelock import FileLock as _FileLock
from filelock import Timeout as _FilelockTimeout

FilelockTimeout = _FilelockTimeout
FileLock = _FileLock
from .ansi import remove_ansi


def save_tuple_to_file(filename: str, data: Union[Tuple, List[Tuple]]) -> None:
    """
    Save a tuple to a file using pickle serialization.

    Args:
        filename (str): The name of the file where the tuple will be stored.
        data (Tuple): The tuple to be stored.

    Returns:
        None
    """
    with open(filename, "wb") as file:
        pickle.dump(data, file)


def load_tuple_from_file(filename: str) -> Union[Tuple, List[Tuple]]:
    """
    Load a tuple from a file using pickle deserialization.

    Args:
        filename (str): The name of the file from which to load the tuple.

    Returns:
        Tuple: The tuple that was stored in the file.
    """
    with open(filename, "rb") as file:
        return pickle.load(file)


def write_json(file_path: str, data: Any):
    """
    Write JSON data to a file. Creates parent directories if they do not exist.
    """
    if not data:
        return

    # Ensure parent directories exist
    os.makedirs(os.path.dirname(file_path), exist_ok=True)

    with open(file_path, "w", encoding="utf-8") as file:
        json.dump(data, file, indent=2, ensure_ascii=False, default=serialize)


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


def copy_folder(source_folder: str, destination_folder: str) -> None:
    """
    Copy a folder and its contents recursively from the source location to the destination location.

    Args:
        source_folder (str): The path to the source folder to be copied.
        destination_folder (str): The path to the destination folder where the source folder will be copied.

    Raises:
        FileExistsError: If the destination folder already exists.
        FileNotFoundError: If the source folder does not exist.

    Returns:
        None
    """
    # Ensure destination parent folder exists
    os.makedirs(os.path.dirname(destination_folder), exist_ok=True)

    shutil.copytree(source_folder, destination_folder, dirs_exist_ok=True)


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

    folders: List[str] = [
        os.path.join(directory, folder)
        for folder in os.listdir(directory)
        if os.path.isdir(os.path.join(directory, folder))
    ]

    if not folders:
        raise ValueError("There are no subdirectories in the specified directory.")

    return os.path.normpath(random.choice(folders))


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
        with open(file_path, "r", encoding="utf-8") as file:
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
        with open(file_path, "w", encoding="utf-8") as file:
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
        with open(filename, "a+", encoding="utf-8") as file:
            # file.write(string_to_add.encode("utf-8").decode("utf-8", "ignore"))
            file.write(f"{remove_ansi(remove_non_ascii(string_to_add))}\n")
    except Exception as e:
        print(f"Fail append new line {filename} {e.args[0]}")
        pass


def fix_permissions(
    path: str,
    desired_permissions: int = stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO,
) -> None:
    """
    Fixes the permissions of a folder.

    Args:
        path (str): The path to the folder whose permissions need to be fixed.
        desired_permissions (int): The desired permissions for the folder in octal format.

    Returns:
        None

    Raises:
        OSError: If there is an error changing the folder's permissions.
    """
    try:
        # Change the folder permissions
        os.chmod(path, desired_permissions)
    except OSError as e:
        print(f"Error fix perm {path}: {e}")


def resolve_folder(path: str) -> str:
    resolve_parent_folder(path)
    os.makedirs(path, exist_ok=True)
    fix_permissions(path)
    return path


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


def sanitize_filename(filename: str, extra_chars: Optional[str] = None) -> str:
    """
    Sanitize the given filename by replacing invalid characters with hyphens.

    Args:
        filename (str): The original filename to be sanitized.
        extra_chars (Optional[str]): Additional characters to be replaced with hyphens.
                                      If None, defaults to the standard set of invalid characters.

    Returns:
        str: The sanitized filename with invalid characters replaced by hyphens.
    """
    # Define a regular expression to match invalid characters in filenames
    # All non-word and non-digit characters except - _ space and .
    invalid_chars = r"[^\w\s.\-]"

    # Replace invalid characters with hyphens
    sanitized_filename = re.sub(invalid_chars, "-", filename)

    # If extra_chars is provided, add them to the invalid_chars pattern
    if extra_chars:
        extra_chars_pattern = re.escape(extra_chars)
        invalid_chars = f"[{extra_chars_pattern}]"
        # Re-replace invalid characters with hyphens
        sanitized_filename = re.sub(invalid_chars, "-", sanitized_filename)

    return remove_trailing_hyphens(sanitized_filename)


def truncate_file_content(file_path, max_length=0):
    if os.path.exists(file_path):
        try:
            with open(file_path, "r+", encoding="utf-8") as file:
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


def read_all_text_files(directory: str) -> Dict[str, str]:
    """
    Read all text files in directory
    """
    os.makedirs(directory, 777, exist_ok=True)
    text_files_content = {}

    # List all files in the directory
    for filename in os.listdir(directory):
        if filename.endswith(".txt"):
            file_path = os.path.join(directory, filename)
            try:
                with open(file_path, "r", encoding="utf-8") as file:
                    text_files_content[file_path] = file.read()
            except Exception as e:
                print(f"Error reading {file_path}: {e}")

    return text_files_content


def serialize(obj):
    """Serialize an object to a dictionary."""
    if hasattr(obj, "__dict__"):
        return obj.__dict__
    elif isinstance(obj, (int, float, str, bool, type(None))):
        return obj
    else:
        raise TypeError(f"Object of type {type(obj)} is not JSON serializable")


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
    with open(source_file, "r+", encoding="utf-8") as source:
        lines = source.readlines()

    with open(destination_file, "a+", encoding="utf-8") as destination:
        destination.writelines(lines[:n])

    with open(source_file, "w+", encoding="utf-8") as source:
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
    with open(file_path, "r", encoding="utf-8") as file:
        # Use a loop to iterate through each line and count them
        line_count = sum(1 for line in file)

    return line_count


def remove_non_ascii(input_string):
    """
    Remove non-ASCII characters from the input string.
    """
    return re.sub(r"[^\x00-\x7F]+", "", input_string)


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
    fix_permissions(parent_folder)
    return parent_folder


def remove_string_from_file(
    file_path: str, strings_to_remove: Union[str, List[str]]
) -> None:
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
    random_string = "".join(random.choice(string.ascii_letters) for _ in range(5))
    temp_file_path = f"tmp/runners/{random_string}.txt"
    os.makedirs(os.path.dirname(temp_file_path), exist_ok=True)

    # Define a lock file path
    id_file_lock = hashlib.md5(file_path.encode("utf-8"))
    lock_file_path = f"tmp/runners/{id_file_lock}.lock"
    lock = FileLock(lock_file_path)

    try:
        # Attempt to acquire the lock with a timeout
        with lock.acquire(timeout=10):  # Timeout after 10 seconds
            # Open the original file and the temporary file
            with open(file_path, "r", encoding="utf-8") as file, open(
                temp_file_path, "w", encoding="utf-8"
            ) as temp_file:
                for line in file:
                    # Replace all occurrences of the pattern with an empty string
                    modified_line = regex.sub("", line)
                    temp_file.write(modified_line)

            # Replace the original file with the temporary file
            shutil.move(temp_file_path, file_path)
    except FilelockTimeout:
        print(f"Could not acquire lock for {file_path}. The operation is skipped.")


def remove_trailing_hyphens(string: Optional[str]) -> str:
    """
    Remove trailing hyphens from the given string.

    Args:
        string (Optional[str]): The string with potential trailing hyphens.

    Returns:
        str: The string with trailing hyphens removed.
    """
    if not string:
        return ""
    # Remove trailing hyphens, accounting for spaces and empty strings
    return re.sub(r"[-\s]+$", "", string).strip()


def file_remove_empty_lines(file_path: str) -> None:
    """
    Remove empty lines from a file.

    Args:
        file_path (str): The path to the input file.

    Returns:
        None
    """
    temp_file = os.path.join("tmp", md5(file_path) + ".tmp")
    try:
        with open(file_path, "r", encoding="utf-8") as f_in, open(
            temp_file, "w", encoding="utf-8"
        ) as f_out:
            for line in f_in:
                if line.strip():  # Check if the line is not empty
                    f_out.write(line)
        # Replace the original file with the temporary file
        shutil.move(temp_file, file_path)
        # print("Empty lines removed from", file_path)
    except Exception:
        # print("File not found.")
        pass


def md5(input_string):
    md5_hash = hashlib.md5(input_string.encode()).hexdigest()
    return md5_hash


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
    with open(
        filename, "r", encoding="utf-8"
    ) as original_file, tempfile.NamedTemporaryFile(
        mode="w", encoding="utf-8", delete=False
    ) as temp_file:
        lines_seen = set()  # Set to store unique lines
        for line in original_file:
            if line not in lines_seen:
                temp_file.write(line)
                lines_seen.add(line)

    # Replace original file with cleaned content
    temp_filename = temp_file.name
    import shutil

    shutil.move(temp_filename, filename)


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


def is_file_larger_than_kb(file_path, size_in_kb=5):
    # Get the size of the file in bytes
    file_size_bytes = os.path.getsize(file_path)

    # Convert bytes to kilobytes
    file_size_kb = file_size_bytes / 1024

    # Check if file size is greater than specified size
    return file_size_kb > size_in_kb


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
