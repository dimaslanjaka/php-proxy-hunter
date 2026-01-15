import os
import sys

# Add project root to sys.path
sys.path.insert(
    0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../"))
)

from src.vps.vps_connector import VPSConnector
from src.func import get_relative_path


def sync_backups(vps: VPSConnector):
    # Run remote backup-db first
    remote_backup_bash = f"{vps.remote_path.rstrip('/')}/bin/backup-db"
    vps.run_command(remote_backup_bash, vps.remote_path)
    local_backup_dir = get_relative_path("backups")
    # Use connector's remote_path to find backups folder on server
    remote_backup_dir = f"{vps.remote_path.rstrip('/')}/backups"
    # Perform sync: remote -> local (delete extra local files by default)
    vps.sync_remote_to_local(
        remote_backup_dir,
        local_backup_dir,
        delete_extra=True,
        exclude="*.py",
        compare="size",
    )
    print("Backups folder synced (remote -> local).")


def register():
    return {"label": "Sync Backups Folder (remote→local)", "action": sync_backups}
