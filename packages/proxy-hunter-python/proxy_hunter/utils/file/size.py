import os
import sys
from typing import List


def count_lines_in_file(file_path: str) -> int:
    """
    Count the number of lines in a given file.

    Args:
        file_path (str): The path to the file to be counted.

    Returns:
        int: The total number of lines in the file.
    """
    with open(file_path, "r", encoding="utf-8") as file:
        return sum(1 for _ in file)


def size_of_list_in_mb(list_of_strings: List[str]) -> float:
    """
    Calculate the size of a list of strings in megabytes.

    Args:
        list_of_strings (List[str]): The list of strings to calculate the size of.

    Returns:
        float: The size of the list in megabytes.
    """
    total_size = sum(sys.getsizeof(s) for s in list_of_strings)
    return total_size / (1024 * 1024)


def is_file_larger_than_kb(file_path: str, size_in_kb: int = 5) -> bool:
    """
    Check if a file is larger than a given number of kilobytes.

    Args:
        file_path (str): Path to the file.
        size_in_kb (int): Threshold size in KB. Defaults to 5 KB.

    Returns:
        bool: True if file size is greater than size_in_kb, False otherwise.
    """
    file_size_bytes = os.path.getsize(file_path)
    file_size_kb = file_size_bytes / 1024
    return file_size_kb > size_in_kb
