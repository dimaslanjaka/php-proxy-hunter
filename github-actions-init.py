import os
import shutil
import stat


def delete_path(path: str) -> None:
    """
    Delete a folder or file specified by the path if it exists.

    Args:
        path (str): The path of the folder or file to delete.
    """
    if not os.path.exists(path):
        print(f"Path '{path}' does not exist.")
        return

    try:
        if os.path.isdir(path):
            shutil.rmtree(path, ignore_errors=True)
            print(f"Folder '{path}' and its contents deleted successfully.")
        elif os.path.isfile(path):
            os.remove(path)
            print(f"File '{path}' deleted successfully.")
        else:
            print(f"Path '{path}' is neither a file nor a folder.")
    except OSError as e:
        print(f"Error deleting '{path}': {e}")


temp_dirs = ["tmp/runners", "tmp/logs"]
dirs = [
    ".cache",
    "config",
    "assets/proxies",
    "tmp/cookies",
    "assets/chrome-profiles",
    "assets/chrome",
    "tmp/data",
    "dist",
] + temp_dirs

# Delete directories contents
for dir_path in temp_dirs:
    if os.path.exists(dir_path):
        delete_path(dir_path)

# Create directories
for dir_path in dirs:
    current_directory = os.path.dirname(os.path.abspath(__file__))
    dir_path = os.path.join(current_directory, dir_path)
    os.makedirs(dir_path, exist_ok=True)
    # Set permissions to 777
    os.chmod(dir_path, stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)
