import os
import shutil


def copy_file(source_file: str, destination_file: str) -> None:
    """
    Copy a file from source to destination, overwriting if the destination file exists.

    Parameters:
    - source_file (str): Path to the source file.
    - destination_file (str): Path to the destination file.

    Returns:
    - None
    """
    try:
        # Copy file, overwriting destination if it exists
        shutil.copyfile(source_file, destination_file)
        print(f"File '{source_file}' copied to '{destination_file}'")
    except FileNotFoundError:
        print(f"Error: File '{source_file}' not found.")
    except Exception as e:
        print(f"Error: {e}")


def copy_folder(source_folder: str, destination_folder: str) -> None:
    """
    Copy a folder and its contents recursively from the source location to the destination location.

    Args:
        source_folder (str): The path to the source folder to be copied.
        destination_folder (str): The path to the destination folder where the source folder will be copied.

    Raises:
        FileExistsError: If the destination folder already exists.
        FileNotFoundError: If the source folder does not exist.

    Returns:
        None
    """
    # Ensure destination parent folder exists
    os.makedirs(os.path.dirname(destination_folder), exist_ok=True)

    shutil.copytree(source_folder, destination_folder, dirs_exist_ok=True)
