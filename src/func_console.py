import inspect
import os
import platform
import re
import subprocess
import sys
import threading
from typing import Any, Dict, Optional, Union

from colorama import init, Fore, Style

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

# Import `is_debug` lazily inside `debug_log()` to avoid import-time side-effects
# Initialize colorama (always) so Windows streams are wrapped for color handling.
init(autoreset=True, strip=False, convert=False)


def red(text: str | int | float | None) -> str:
    """Return text in bright red."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.RED}{text}{Style.RESET_ALL}"


def magenta(text: str | int | float | None) -> str:
    """Return text in bright magenta."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.MAGENTA}{text}{Style.RESET_ALL}"


def yellow(text: str | int | float | None) -> str:
    """Return text in bright yellow."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.YELLOW}{text}{Style.RESET_ALL}"


def green(text: str | int | float | None) -> str:
    """Return text in bright green."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.GREEN}{text}{Style.RESET_ALL}"


def cyan(text: str | int | float | None) -> str:
    """Return text in bright cyan."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.CYAN}{text}{Style.RESET_ALL}"


def orange(text: str | int | float | None) -> str:
    """Return text in bright orange."""
    orange_color = "\033[38;5;208m"
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{orange_color}{text}{Style.RESET_ALL}"


def blue(text: str | int | float | None) -> str:
    """Return text in bright blue."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.BLUE}{text}{Style.RESET_ALL}"


def white(text: str | int | float | None) -> str:
    """Return text in bright white."""
    if text is None:
        text = ""
    else:
        text = str(text)
    return f"{Style.BRIGHT}{Fore.WHITE}{text}{Style.RESET_ALL}"


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


css_content: str = ""


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


def log_file(filename: Optional[str] = None, *args: Any, **kwargs: Any) -> None:
    """Log messages to a file with optional ANSI or HTML handling.

    Args:
        filename (str): The path to the log file.
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to False.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    global css_content
    should_remove_ansi = kwargs.pop("remove_ansi", False)
    ansi_html = kwargs.pop("ansi_html", False)
    print_args = kwargs.pop("print_args", True)
    end = kwargs.pop("end", "\n")
    file_path = filename
    message = " ".join(map(str, args))
    if print_args:
        with print_lock:
            sys.stdout.write(f"{message}{end}")
            sys.stdout.flush()

    if ansi_html:
        from ansi2html import Ansi2HTMLConverter
        from bs4 import BeautifulSoup
        import bs4 as _bs4

        conv = Ansi2HTMLConverter()
        html_content = conv.convert(message)
        soup = BeautifulSoup(html_content, "html.parser")
        pre_tag = soup.find("pre", class_="ansi2html-content")
        if isinstance(pre_tag, _bs4.element.Tag):
            message = pre_tag.decode_contents().strip()
            style_tag = soup.find("style")
            if isinstance(style_tag, _bs4.element.Tag):
                css_text = style_tag.get_text()
                if css_text not in css_content:
                    css_content += f"{css_text}\n\n"
            else:
                message += (
                    "\nFail convert ANSI to HTML. style_tag is not type of element Tag"
                )
        else:
            message = "Fail convert ANSI to HTML. pre_tag is not type of element Tag"
    elif should_remove_ansi:
        from proxy_hunter import remove_ansi

        message = remove_ansi(message)

    if file_path:
        try:
            dirname = os.path.dirname(file_path)
            if dirname:
                os.makedirs(dirname, exist_ok=True)

            with open(file_path, "a", encoding="utf-8") as f:
                f.write(message + "\n")
        except Exception as e:
            log_error(f"log_file cannot write {file_path}: {e}")


def log_proxy(*args: Any, **kwargs: Any) -> None:
    """
    Proxy logging function to 'proxyChecker.txt'.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to False.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    log_file("proxyChecker.txt", *args, **kwargs)


def log_error(*args: Any, **kwargs: Any) -> None:
    """
    Log error messages to 'errorLog.txt'.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to False.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    log_file("tmp/logs/error.txt", *args, **kwargs)


def debug_log(*args: Any, **kwargs: Any) -> None:
    """Log debugging information to the console and a debug file."""
    from src.func_platform import is_debug
    from proxy_hunter import write_file

    if is_debug():
        sep = kwargs.get("sep", " ")
        end = kwargs.get("end", "\n")
        message = sep.join(map(str, args)) + end
        # Print to console
        print(message, end="")
        # Write to file
        file_path = "tmp/debug.log"
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


browser_output_log = "tmp/runners/result.txt"


def log_browser(*args: Any, **kwargs: Any) -> None:
    """Log browser output to a file.

    Args:
        *args (Any): Positional arguments representing the messages to log.
        **kwargs (Any): Optional keyword arguments:
            - remove_ansi (bool): Removes ANSI color codes. Defaults to False.
            - ansi_html (bool): Converts ANSI to HTML. Defaults to False.
            - print_args (bool): Prints the message to stdout. Defaults to True.
            - end (str): String appended after the message when printing. Defaults to newline.
    """
    global browser_output_log
    log_file(browser_output_log, *args, **kwargs)


def read_log_file(log_file_path: str) -> str:
    """Read a log file and return its content as HTML.

    Args:
        log_file_path (str): The path to the log file.

    Returns:
        str: The log file content converted into an HTML structure,
             with ANSI escape codes optionally removed and CSS applied.
    """
    from proxy_hunter import read_file

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


def clear_console():
    system = platform.system()
    if system == "Windows":
        os.system("cls")
    else:
        os.system("clear")


def color_percent_value_text(value: float | int | None, text: str) -> str:
    """Return `text` colored on a smooth red->green gradient for `value` in 0..100.

    Uses 24-bit ANSI escape sequence for a smooth transition where
    0 -> red (255,0,0), 100 -> green (0,255,0). If `value` is None,
    returns the original `text` unchanged.
    """
    if value is None:
        return text

    try:
        # Normalize and clamp value to 0..100
        v = float(value)
    except Exception:
        return text

    if v < 0:
        v = 0.0
    if v > 100:
        v = 100.0

    t = v / 100.0
    # Interpolate red->green via simple linear mix: R = 255*(1-t), G = 255*t
    r = int(255 * (1.0 - t))
    g = int(255 * t)
    b = 0

    # Use 24-bit (truecolor) ANSI escape. Keep bright style for consistency.
    ansi = f"\033[38;2;{r};{g};{b}m"
    return f"{Style.BRIGHT}{ansi}{text}{Style.RESET_ALL}"
