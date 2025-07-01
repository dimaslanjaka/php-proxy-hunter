import os
import sys

# Add project root to sys.path
sys.path.insert(
    0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../"))
)

from src.vps.vps_connector import VPSConnector
from src.func import get_relative_path


def register():
    return {"label": "Download Backups Folder", "action": download_backups}


def download_backups(vps: VPSConnector):
    local_backup_dir = get_relative_path("backups")
    remote_backup_dir = "/var/www/html/backups/"
    vps.download(remote_backup_dir, local_backup_dir)
    print("Backups folder downloaded.")
