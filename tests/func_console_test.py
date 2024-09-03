import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_relative_path, read_file, truncate_file_content
from src.func_console import contains_ansi_codes, green, log_file, magenta, red, yellow

if __name__ == "__main__":
    messages = (
        red("Red Text"),
        green("Green Text"),
        magenta("Magenta Text"),
        yellow("Yellow Text"),
    )

    clean_file = get_relative_path("tmp/data/test-clean-ansi.txt")
    truncate_file_content(clean_file)
    log_file(clean_file, print_args=False, *messages)
    read = read_file(clean_file)
    print("clean_file should not have ANSI ", contains_ansi_codes(read) == False)

    ansi_file = get_relative_path("tmp/data/test-with-ansi.txt")
    truncate_file_content(ansi_file)
    log_file(ansi_file, print_args=False, remove_ansi=False, *messages)
    read = read_file(ansi_file)
    print("ansi_file should contains ANSI ", contains_ansi_codes(read) == True)

    html_file = get_relative_path("tmp/data/test-with-ansi.html")
    truncate_file_content(html_file)
    log_file(html_file, print_args=False, ansi_html=True, *messages)
    read = read_file(html_file)
    print("html_file should not have ANSI ", contains_ansi_codes(read) == False)