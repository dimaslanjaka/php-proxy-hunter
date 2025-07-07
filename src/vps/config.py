from typing import Any, Dict
import os
import json


def load_sftp_config(config_path: str = ".vscode/sftp.json") -> Dict[str, Any]:
    """
    Load SFTP/SSH configuration from a JSON file.
    """
    if not os.path.exists(config_path):
        raise FileNotFoundError(f"SFTP config file not found: {config_path}")
    with open(config_path, "r") as f:
        config: Dict[str, Any] = json.load(f)
    return {
        "host": config["host"],
        "port": config.get("port", 22),
        "username": config["username"],
        "password": config.get("password"),
        "key_path": (
            os.path.expanduser(config.get("privateKeyPath", ""))
            if config.get("privateKeyPath")
            else None
        ),
        "remote_path": config.get("remotePath", "/"),
    }
