import os
import sys
import glob
import importlib.util

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))
from src.func import get_relative_path
from src.vps.vps_connector import VPSConnector, load_sftp_config


def load_menus():
    """Dynamically load menu items from Python files in the menus directory."""
    menu_items = []
    for file_path in glob.glob(
        os.path.join(os.path.dirname(__file__), "menus", "*.py")
    ):
        module_name = os.path.splitext(os.path.basename(file_path))[0]
        if module_name == "__init__":
            continue
        spec = importlib.util.spec_from_file_location(module_name, file_path)
        if spec is not None and spec.loader is not None:
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)
            result = module.register()
            if isinstance(result, list):
                menu_items.extend(result)
            else:
                menu_items.append(result)
    return sorted(menu_items, key=lambda x: x["label"])


def pull_latest_code(vps: VPSConnector, g):
    """Pull the latest code from the git repository on the VPS."""
    return vps.run_command_live("git pull", "/var/www/html")


if __name__ == "__main__":
    sftp_config = load_sftp_config(get_relative_path(".vscode/sftp.json"))

    vps = VPSConnector(
        host=sftp_config["host"],
        port=sftp_config["port"],
        username=sftp_config["username"],
        password=sftp_config["password"],
        key_path=sftp_config["key_path"],
        remote_path=sftp_config.get("remote_path", "/var/www/html"),
        local_path=sftp_config.get("local_path", "."),
    )

    try:
        vps.connect()
        menu_items = [
            {
                "label": "Pull latest code (git pull)",
                "action": pull_latest_code,
            }
        ]

        # Add dynamically loaded plugins
        menu_items += load_menus()

        # Show menu
        print("Select an action to perform:")
        for idx, item in enumerate(menu_items, 1):
            print(f"{idx}. {item['label']}")

        choice = input(f"Enter your choice (1-{len(menu_items)}): ").strip()
        if choice.isdigit():
            idx = int(choice) - 1
            if 0 <= idx < len(menu_items):
                menu_items[idx]["action"](vps)
            else:
                print("Invalid choice.")
        else:
            print("Invalid input.")
    finally:
        vps.close()
