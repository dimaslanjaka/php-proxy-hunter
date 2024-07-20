import inspect
import re
import subprocess
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from colorama import just_fix_windows_console, Style, Fore

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


def remove_color_codes(text):
    # Define the regex pattern for ANSI color codes
    ansi_escape = re.compile(r"\x1B[@-_][0-?]*[ -/]*[@-~]")
    # Remove the ANSI color codes
    return ansi_escape.sub("", text)


def log_proxy(*args, **kwargs):
    # Convert all arguments to string and join them with a space
    message = " ".join(map(str, args))

    # Print to console (with color codes)
    print(message, **kwargs)

    # Remove color codes before logging to file
    cleaned_message = remove_color_codes(message)

    # Log to file
    with open(get_relative_path("proxyChecker.txt"), "a", encoding="utf-8") as f:
        f.write(cleaned_message + "\n")


def log_file(filename: str, *args, **kwargs):
    # Convert all arguments to string and join them with a space
    message = " ".join(map(str, args))

    # Print to console (with color codes)
    print(message, **kwargs)

    # Remove color codes before logging to file
    cleaned_message = remove_color_codes(message)

    # Log to file
    with open(filename, "a", encoding="utf-8") as f:
        f.write(cleaned_message + "\n")
