from pathlib import Path
from typing import List, Union, Set


def remove_string_from_file(
    file_path: Union[str, Path], data: Union[str, List[str], Set[str]]
) -> bool:
    """
    Remove all occurrences of a specific string from a file.

    Args:
        file_path: Path to the file from which to remove the string.
        data: The string or list of strings to be removed from the file.
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

        with file_path.open("w", encoding="utf-8") as f:
            f.write(new_content)

        return True
    except Exception as e:
        print(f"Error removing string from file: {e}")
        return False
