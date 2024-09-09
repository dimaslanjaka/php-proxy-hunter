import inspect
import os
import re
import subprocess
import sys
import threading
from typing import Any, Dict, Union

from ansi2html import Ansi2HTMLConverter
from bs4 import BeautifulSoup
from colorama import Fore, Style, just_fix_windows_console
from proxy_hunter import remove_ansi, resolve_parent_folder

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path
from src.func_platform import is_debug

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


css_content = ""


def get_ansi_css_content():
    """Get generated CSS content (unique)"""
    global css_content
    lines = re.split(r"\r?\n", css_content)
    unique_lines = list(set(lines))
    result = "\n".join(unique_lines)
    return result


print_lock = threading.Lock()


def log_file(filename: str, *args: Any, **kwargs: Any) -> None:
    """
    Logs messages to a specified file, with optional handling for ANSI color codes and HTML conversion.

    Args:
        filename (str): The path to the log file where messages will be appended.
        *args (Any): Positional arguments representing the messages to log.
                     Each argument is converted to a string and concatenated with a space.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool, optional): If True (default), removes ANSI color codes from the log message.
            - ansi_html (bool, optional): If True, converts ANSI color codes to HTML format. Defaults to False.
            - print_args (bool, optional): If True (default), prints the message to stdout before logging.
            - end (str, optional): The string appended after the message when printing. Defaults to a newline ("\n").

    Returns:
        None
    """
    global css_content
    should_remove_ansi = kwargs.pop("remove_ansi", True)
    ansi_html = kwargs.pop("ansi_html", False)
    print_args = kwargs.pop("print_args", True)
    end = kwargs.pop("end", "\n")
    message = " ".join(map(str, args))
    if print_args:
        with print_lock:
            sys.stdout.write(f"{message}{end}")
            sys.stdout.flush()

    if ansi_html:
        conv = Ansi2HTMLConverter()
        html_content = conv.convert(message)
        soup = BeautifulSoup(html_content, "html.parser")
        pre_tag = soup.find("pre", class_="ansi2html-content")
        message = pre_tag.decode_contents().strip()
        style_tag = soup.find("style")
        css_content += style_tag.get_text() + "\n\n"
    elif should_remove_ansi:
        message = remove_ansi(message)

    os.makedirs(os.path.dirname(filename), exist_ok=True)

    with open(filename, "a", encoding="utf-8") as f:
        f.write(message + "\n")


def log_proxy(*args: Any, **kwargs: Any) -> None:
    """
    Proxy function to log messages to 'proxyChecker.txt' using log_file.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Keyword arguments to pass to log_file, including:
            - remove_ansi (bool, optional): If True (default), removes ANSI color codes from the log message.
            - ansi_html (bool, optional): If True, converts ANSI color codes to HTML format. Defaults to False.
            - print_args (bool, optional): If True (default), prints the message to stdout before logging.
            - end (str, optional): The string appended after the message when printing. Defaults to a newline ("\n").

    Returns:
        None
    """
    log_file(get_relative_path("proxyChecker.txt"), *args, **kwargs)


def log_error(*args: Any, **kwargs: Any) -> None:
    """
    Log error messages to 'errorLog.txt' using log_file.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Keyword arguments to pass to log_file, including:
            - remove_ansi (bool, optional): If True (default), removes ANSI color codes from the log message.
            - ansi_html (bool, optional): If True, converts ANSI color codes to HTML format. Defaults to False.
            - print_args (bool, optional): If True (default), prints the message to stdout before logging.
            - end (str, optional): The string appended after the message when printing. Defaults to a newline ("\n").

    Returns:
        None
    """
    log_file(get_relative_path("tmp/logs/error.txt"), *args, **kwargs)


def debug_log(*args, **kwargs) -> None:
    """
    Log debugging information to the console if the current device matches a specific device name.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Keyword arguments to pass to log_file, including:
            - remove_ansi (bool, optional): If True (default), removes ANSI color codes from the log message.
            - ansi_html (bool, optional): If True, converts ANSI color codes to HTML format. Defaults to False.
            - print_args (bool, optional): If True (default), prints the message to stdout before logging.
            - end (str, optional): The string appended after the message when printing. Defaults to a newline ("\n").
    """
    if is_debug():
        sep = kwargs.get("sep", " ")
        end = kwargs.get("end", "\n")
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


browser_output_log = get_relative_path("tmp/runners/result.txt")


def log_browser(*args, **kwargs):
    """
    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Keyword arguments to pass to log_file, including:
            - remove_ansi (bool, optional): If True (default), removes ANSI color codes from the log message.
            - ansi_html (bool, optional): If True, converts ANSI color codes to HTML format. Defaults to False.
            - print_args (bool, optional): If True (default), prints the message to stdout before logging.
            - end (str, optional): The string appended after the message when printing. Defaults to a newline ("\n").
    """
    global browser_output_log
    log_file(browser_output_log, *args, **kwargs)
