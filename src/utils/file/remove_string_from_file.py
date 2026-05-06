from pathlib import Path
from typing import List, Union, Set


def remove_string_from_file(
    file_path: Union[str, Path],
    data: Union[str, List[str], Set[str]],
    clear_trailing_empty_lines: bool = False,
) -> bool:
    """
    Remove all occurrences of a specific string from a file.

    Args:
        file_path: Path to the file from which to remove the string.
        data: The string or list of strings to be removed from the file.
        clear_trailing_empty_lines: If True, remove trailing empty/whitespace-only
            lines at the end of the file (so the file does not end with
            multiple blank lines). Defaults to False.
    """
    try:
        file_path = Path(file_path)
        if not file_path.is_file():
            print(f"File {file_path} does not exist.")
            return False

        with file_path.open("r", encoding="utf-8") as f:
            content = f.read()

        # Support removing a single string or multiple strings (list/tuple/set)
        new_content = content
        if isinstance(data, (list, tuple, set)):
            for item in data:
                if not item:
                    continue
                new_content = new_content.replace(str(item), "")
        else:
            if data:
                new_content = new_content.replace(str(data), "")

        # Optionally remove trailing empty lines at end of file
        if clear_trailing_empty_lines:
            # splitlines() drops final empty trailing line markers, so we'll
            # operate on logical lines and then rejoin with a single '\n'
            lines = new_content.splitlines()
            while lines and lines[-1].strip() == "":
                lines.pop()
            new_content = "\n".join(lines)
            # If there is remaining content, ensure it ends with a single newline
            if new_content and not new_content.endswith("\n"):
                new_content += "\n"

        with file_path.open("w", encoding="utf-8") as f:
            f.write(new_content)

        return True
    except Exception as e:
        print(f"Error removing string from file: {e}")
        return False
