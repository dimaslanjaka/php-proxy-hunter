from typing import Any, Dict, Optional
import os
import sys
import paramiko

# Add project root to sys.path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

from src.vps.ssh_client import SSHClient
from src.vps.sftp_client import SFTPClient
from src.vps.config import load_sftp_config


class VPSConnector(SSHClient, SFTPClient):
    """
    High-level orchestrator for SSH and SFTP operations on a VPS.
    Inherits from SSHClient and SFTPClient so all methods are available directly.
    """

    client: Optional[paramiko.SSHClient]
    sftp: Optional[paramiko.SFTPClient]

    def __init__(
        self,
        host: str,
        port: int,
        username: str,
        remote_path: str,
        local_path: str = os.getcwd(),
        password: Optional[str] = None,
        key_path: Optional[str] = None,
    ):
        """
        Initialize VPSConnector with SSH and SFTP capabilities.
        """
        super().__init__(host, port, username, password, key_path)
        self.remote_path = remote_path
        self.local_path = local_path
        self.sftp = None  # Will be initialized after SSH connect

    def connect(self) -> None:
        """
        Establish SSH connection and initialize SFTP client.
        """
        print(f"Connecting to {self.host}...")
        super().connect()
        if self.client is None:
            raise RuntimeError("SSH connection failed: client is None.")
        self.sftp = self.client.open_sftp()
        print("🟢\tConnected.")

    def close(self) -> None:
        """
        Close SFTP and SSH connections.
        """
        if self.sftp:
            self.sftp.close()
            self.sftp = None
        super().close()
        print("🔴\tConnection closed.")


# === Example Usage ===
if __name__ == "__main__":
    sftp_config = load_sftp_config()
    vps = VPSConnector(
        host=sftp_config["host"],
        port=sftp_config["port"],
        username=sftp_config["username"],
        password=sftp_config["password"],
        key_path=sftp_config["key_path"],
        remote_path=sftp_config.get("remote_path", "/"),
        local_path=os.getcwd(),
    )
    try:
        vps.connect()
        vps.run_command("uptime")
        # vps.run_command("git pull", "/var/www/html")
        print("Downloading backups from remote server...")
        vps.download(f"{vps.remote_path}/backups", "backups")
    finally:
        vps.close()
