import os
import stat


def make_dirs_and_set_permissions():
    # List of directories to create
    dirs = [".cache", "config", "assets/proxies", "tmp/runners", "tmp/cookies"]

    # Create directories
    for dir_path in dirs:
        current_directory = os.path.dirname(os.path.abspath(__file__))
        dir_path = os.path.join(current_directory, dir_path)
        os.makedirs(dir_path, exist_ok=True)
        # Set permissions to 777
        os.chmod(dir_path, stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)


make_dirs_and_set_permissions()
