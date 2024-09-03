import inspect
import os
import re
import subprocess
import sys
from typing import Any
from bs4 import BeautifulSoup
from ansi2html import Ansi2HTMLConverter
from colorama import Fore, Style, just_fix_windows_console

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path

just_fix_windows_console()


class ConsoleColor:
    """A helper class for colorizing and formatting console output."""

    # ANSI escape codes for text colors
    COLORS = {
        "reset": "\033[0m",
        "black": "\033[30m",
        "red": "\033[31m",
        "green": "\033[32m",
        "yellow": "\033[33m",
        "blue": "\033[34m",
        "purple": "\033[35m",
        "cyan": "\033[36m",
        "white": "\033[37m",
    }

    @classmethod
    def colorize(cls, text: str, color: str = "reset") -> str:
        """Colorize the specified text.

        Args:
            text (str): The text to be colorized.
            color (str, optional): The color name. Defaults to 'reset'.

        Returns:
            str: The colorized text.
        """
        color_code = cls.COLORS.get(color, cls.COLORS["reset"])
        reset_code = cls.COLORS["reset"]
        return f"{color_code}{text}{reset_code}"


def red(text: str):
    # return ConsoleColor.colorize(text, "red")
    return Style.BRIGHT + Fore.RED + text + Style.RESET_ALL


def magenta(text: str):
    return Style.BRIGHT + Fore.MAGENTA + text + Style.RESET_ALL


def yellow(text: str):
    return Style.BRIGHT + Fore.YELLOW + text + Style.RESET_ALL


def green(text: str):
    # return ConsoleColor.colorize(text, "green")
    return Style.BRIGHT + Fore.GREEN + text + Style.RESET_ALL


def orange(text: str):
    orange_color = "\033[38;5;208m"
    return Style.BRIGHT + orange_color + text + Style.RESET_ALL


def restart_script():
    print("restarting....", end=" ")
    python_executable = sys.executable
    script_path = sys.argv[0]
    command = [python_executable, script_path] + sys.argv[1:]
    print(command)
    subprocess.run(command)


def get_caller_info():
    """
    Get the caller's frame from the call stack

    Example:
        ```
        file, line = get_caller_info()
        print(f"Called from file '{file}', line {line}")
        ```
    """
    caller_frame = inspect.stack()[2]
    caller_file = caller_frame.filename
    caller_line = caller_frame.lineno
    return caller_file, caller_line


def ansi_remover(text: str) -> str:
    """
    Remove ANSI color codes from a given text.

    Args:
        text (str): The input text containing ANSI color codes.

    Returns:
        str: The text with ANSI color codes removed.
    """
    ansi_escape = re.compile(r"\x1B[@-_][0-?]*[ -/]*[@-~]")
    return ansi_escape.sub("", text)


css_content = ""


def get_ansi_css_content():
    """Get generated CSS content (unique)"""
    global css_content
    lines = re.split(r"\r?\n", css_content)
    unique_lines = list(set(lines))
    result = "\n".join(unique_lines)
    return result


def log_file(filename: str, *args: Any, **kwargs: Any) -> None:
    """
    Log messages to a file and optionally remove ANSI color codes.

    Args:
        filename (str): The path to the log file.
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Keyword arguments including:
            - remove_ansi (bool): If True, removes ANSI color codes before logging to the file. Defaults to True.
            - ansi_html (bool): If True, convert ANSI color codes to HTML format. Defaults to False.
            - print_args (bool): execute print. Defaults to True.

    Returns:
        None
    """
    global css_content
    remove_ansi = kwargs.pop("remove_ansi", True)
    ansi_html = kwargs.pop("ansi_html", False)
    print_args = kwargs.pop("print_args", True)
    message = " ".join(map(str, args))
    if print_args:
        print(message)

    if ansi_html:
        conv = Ansi2HTMLConverter()
        html_content = conv.convert(message)
        soup = BeautifulSoup(html_content, "html.parser")
        pre_tag = soup.find("pre", class_="ansi2html-content")
        message = pre_tag.decode_contents()
        style_tag = soup.find("style")
        css_content += style_tag.get_text() + "\n\n"
    elif remove_ansi:
        message = ansi_remover(message)

    os.makedirs(os.path.dirname(filename), exist_ok=True)

    with open(filename, "a", encoding="utf-8") as f:
        f.write(message.strip() + "\n")


def log_proxy(*args: Any, **kwargs: Any) -> None:
    """
    Proxy function to log messages to 'proxyChecker.txt' using log_file.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Keyword arguments to pass to log_file, including:
            - remove_ansi (bool): If True, removes ANSI color codes before logging to the file. Defaults to True.
            - ansi_html (bool): If True, convert ANSI color codes to HTML format. Defaults to False.
            - print_args (bool): execute print. Defaults to True.

    Returns:
        None
    """
    log_file(get_relative_path("proxyChecker.txt"), *args, **kwargs)


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
