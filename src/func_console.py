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
from proxy_hunter import read_file, remove_ansi, resolve_parent_folder

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


def red(text: str) -> str:
    """Return text in bright red."""
    return Style.BRIGHT + Fore.RED + text + Style.RESET_ALL


def magenta(text: str) -> str:
    """Return text in bright magenta."""
    return Style.BRIGHT + Fore.MAGENTA + text + Style.RESET_ALL


def yellow(text: str) -> str:
    """Return text in bright yellow."""
    return Style.BRIGHT + Fore.YELLOW + text + Style.RESET_ALL


def green(text: str) -> str:
    """Return text in bright green."""
    return Style.BRIGHT + Fore.GREEN + text + Style.RESET_ALL


def orange(text: str) -> str:
    """Return text in bright orange."""
    orange_color = "\033[38;5;208m"
    return Style.BRIGHT + orange_color + text + Style.RESET_ALL


def restart_script() -> None:
    """Restart the current script."""
    print("restarting....", end=" ")
    python_executable = sys.executable
    script_path = sys.argv[0]
    command = [python_executable, script_path] + sys.argv[1:]
    print(command)
    subprocess.run(command)


def get_caller_info() -> tuple[str, int]:
    """Get the caller's file and line number.

    Returns:
        tuple: A tuple containing the filename and line number of the caller.
    """
    caller_frame = inspect.stack()[2]
    caller_file = caller_frame.filename
    caller_line = caller_frame.lineno
    return caller_file, caller_line


css_content = ""


def get_ansi_css_content() -> str:
    """Get generated unique CSS content from ANSI styles.

    Returns:
        str: A string of unique CSS lines from ANSI styles.
    """
    global css_content
    lines = re.split(r"\r?\n", css_content)
    unique_lines = list(set(lines))
    result = "\n".join(unique_lines)
    return result


print_lock = threading.Lock()


def log_file(filename: str, *args: Any, **kwargs: Any) -> None:
    """Log messages to a file with optional ANSI or HTML handling.

    Args:
        filename (str): The path to the log file.
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to True.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
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
        css_text = style_tag.get_text()
        if css_text not in css_content:
            css_content += f"{css_text}\n\n"
    elif should_remove_ansi:
        message = remove_ansi(message)

    os.makedirs(os.path.dirname(filename), exist_ok=True)

    with open(filename, "a", encoding="utf-8") as f:
        f.write(message + "\n")


def log_proxy(*args: Any, **kwargs: Any) -> None:
    """
    Proxy logging function to 'proxyChecker.txt'.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to True.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    log_file(get_relative_path("proxyChecker.txt"), *args, **kwargs)


def log_error(*args: Any, **kwargs: Any) -> None:
    """
    Log error messages to 'errorLog.txt'.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to True.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    log_file(get_relative_path("tmp/logs/error.txt"), *args, **kwargs)


def debug_log(*args: Any, **kwargs: Any) -> None:
    """Log debugging information to the console and a debug file."""
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
    """Extract error message from an exception.

    Args:
        e (Union[Exception, Any, Dict]): The exception object.

    Returns:
        str: The error message.
    """
    if isinstance(e, Exception) and e.args:
        return str(e.args[0]).strip()
    else:
        return str(e).strip()


def debug_exception(e: Union[Exception, Any, Dict]) -> str:
    """Extract the full trace from an exception.

    Args:
        e (Union[Exception, Any, Dict]): The exception object.

    Returns:
        str: A detailed error message and trace information.
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
    return f"Not an exception: {str(e)}"


browser_output_log = get_relative_path("tmp/runners/result.txt")


def log_browser(*args: Any, **kwargs: Any) -> None:
    """Log browser output to a file.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to True.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    global browser_output_log
    log_file(browser_output_log, *args, **kwargs)


def read_log_file(log_file_path: str):
    """Read a log file and return its content as HTML.

    Args:
        log_file_path (str): The path to the log file.

    Returns:
        str: The log file content converted into an HTML structure,
             with ANSI escape codes optionally removed and CSS applied.
    """
    content = str(read_file(log_file_path))
    lines = re.split(r"\r?\n", content)
    html_content = (
        """
<html>
<head>
<style>%CSS_CONTENT%</style>
<style>
body {
    font-family: 'UbuntuRegular', sans-serif;
}
</style>
</head>
<body>%BODY%</body>
</html>
                """.strip()
        .replace("%CSS_CONTENT%", get_ansi_css_content())
        .replace("%BODY%", "<br/>".join(lines))
    )
    return html_content
