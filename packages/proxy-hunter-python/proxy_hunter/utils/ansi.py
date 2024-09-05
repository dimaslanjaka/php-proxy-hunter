import re


def remove_ansi(text: str) -> str:
    """
    Remove ANSI color codes from a given text.

    Args:
        text (str): The input text containing ANSI color codes.

    Returns:
        str: The text with ANSI color codes removed.
    """
    ansi_escape = re.compile(r"\x1B[@-_][0-?]*[ -/]*[@-~]")
    return ansi_escape.sub("", text)


def contains_ansi_codes(s: str) -> bool:
    """
    Check if the given string contains ANSI escape codes.

    ANSI escape codes are used for text formatting (e.g., colors) in terminal output.
    They usually start with \x1b[ and end with m, with optional parameters in between.

    Args:
        s (str): The string to check for ANSI escape codes.

    Returns:
        bool: True if the string contains ANSI escape codes, False otherwise.
    """
    # Regular expression to match ANSI escape codes
    ansi_escape = re.compile(r"\x1b\[[0-9;]*m")
    return bool(ansi_escape.search(s))
