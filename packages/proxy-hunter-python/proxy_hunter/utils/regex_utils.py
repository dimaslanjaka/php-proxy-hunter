import re
from typing import Optional


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
