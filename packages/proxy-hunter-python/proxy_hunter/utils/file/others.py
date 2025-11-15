import hashlib
import os
import pickle
import random
import re
import shutil
import string
import sys
import tempfile
from typing import Any, Dict, List, Optional, Set, Tuple, Union
from filelock import FileLock
from filelock import Timeout as FilelockTimeout
from ..ansi import remove_ansi, remove_non_ascii
from .writer import write_file
from ..md5 import md5
from .folder import resolve_parent_folder


def save_tuple_to_file(
    filename: str, data: Union[Tuple, List[Tuple], Dict[str, Tuple]]
) -> None:
    """
    Serialize tuple-like data to a file using pickle.

    This function accepts a single tuple, a list of tuples, or a dictionary
    whose values are tuples and writes the object to `filename` using
    Python's pickle binary format.

    Args:
        filename (str): Path to the output file. Parent directories will be
            created if they do not already exist.
        data (Union[Tuple, List[Tuple], Dict[str, Tuple]]): The object to
            serialize. Supported types are a tuple, a list of tuples, or a
            dict mapping strings to tuples.

    Raises:
        TypeError: If `data` is not one of the supported types.
        OSError: If there is an error creating parent directories or writing the file.
        pickle.PicklingError: If the object cannot be pickled.

    Examples:
        >>> save_tuple_to_file("tmp/out.pkl", (1, 2))
        >>> save_tuple_to_file("tmp/list.pkl", [(1, 2), (3, 4)])
        >>> save_tuple_to_file("tmp/map.pkl", {"a": (1,), "b": (2,)})

    Notes:
        Uses pickle.HIGHEST_PROTOCOL for serialization.
    """
    # Validate supported data types
    if not isinstance(data, (tuple, list, dict)):
        raise TypeError(
            "data must be a tuple, list of tuples, or dict of string->tuple"
        )

    # Ensure parent directory exists and permissions are set
    resolve_parent_folder(filename)

    # Write using highest available pickle protocol
    with open(filename, "wb") as file:
        pickle.dump(data, file, protocol=pickle.HIGHEST_PROTOCOL)


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


# write_json moved to writer.py


# get_random_folder moved to .folder


# write_file moved to writer.py


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


def file_exists(filepath: str) -> bool:
    """
    Check if a file exists at the specified path.

    Args:
        filepath (str): The path to the file.

    Returns:
        bool: True if the file exists and is a regular file, False otherwise.

    Example:
        >>> file_exists("/path/to/file.txt")
        True
    """
    return os.path.isfile(filepath)


# fix_permissions moved to .folder


# resolve_folder moved to .folder


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


# list_files_in_directory moved to .folder


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


# remove_non_ascii moved to ..ansi


# resolve_parent_folder moved to .folder


def remove_string_from_file(
    file_path: str,
    strings_to_remove: Union[str, List[str], Set[str]],
    exact_matches: bool = False,
) -> None:
    """
    Removes all occurrences of specified strings from a file.

    Args:
        file_path (str): The path to the file.
        strings_to_remove (Union[str, List[str], Set[str]]): The string, list of strings, or set of strings to be removed from the file.
        exact_matches (bool): If True, only lines that exactly match any of the strings in `strings_to_remove` will be removed.
                               If False, partial matches are removed.

    Returns:
        None
    """
    if not os.path.exists(file_path):
        return

    # Ensure strings_to_remove is a list
    if isinstance(strings_to_remove, str):
        strings_to_remove = [strings_to_remove]
    elif isinstance(strings_to_remove, set):
        strings_to_remove = list(strings_to_remove)

    # Escape strings and create regex pattern
    escaped_strings = [re.escape(s) for s in strings_to_remove[:1000]]
    pattern = "|".join(escaped_strings)
    if exact_matches:
        pattern = f"^({pattern})$"
    regex = re.compile(pattern)

    # Create a temporary file
    random_string = "".join(random.choice(string.ascii_letters) for _ in range(5))
    temp_file_path = f"tmp/runners/{random_string}.txt"
    os.makedirs(os.path.dirname(temp_file_path), exist_ok=True)

    # Define a lock file path
    id_file_lock = hashlib.md5(file_path.encode("utf-8")).hexdigest()
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


# md5 moved to proxy_hunter.utils.md5


# is_directory_created_days_ago_or_more moved to .folder


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


# join_path moved to .folder
