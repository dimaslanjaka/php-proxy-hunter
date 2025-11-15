from typing import Optional


def read_file(file_path: str) -> Optional[str]:
    """
    Read content from a file.

    Args:
        file_path (str): The path to the file to read.

    Returns:
        Optional[str]: The content of the file if successful, None otherwise.
    """
    try:
        with open(file_path, "r", encoding="utf-8") as file:
            content = file.read()
        return content
    except FileNotFoundError:
        print(f"Error: File '{file_path}' not found.")
        return None
    except Exception as e:
        print(f"Error: An exception occurred - {e}")
        return None
