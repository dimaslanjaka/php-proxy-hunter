from typing import Optional
import paramiko
import os
import stat
import posixpath
from . import sftp_helpers as helpers
from . import sftp_transfer as transfer


class SFTPClient:
    """Handles SFTP upload, download, and file/folder operations."""

    sftp: Optional[paramiko.SFTPClient]

    def __init__(self, ssh_client: paramiko.SSHClient):
        """
        Initialize SFTPClient with a connected paramiko.SSHClient.
        """
        self.sftp: Optional[paramiko.SFTPClient] = ssh_client.open_sftp()

    def _print_upload_progress(self, filename: str, size: int, sent: int) -> None:
        return helpers.print_upload_progress(filename, size, sent)

    def _print_download_progress(self, filename: str, size: int, received: int) -> None:
        return helpers.print_download_progress(filename, size, received)

    def upload(self, local_path: str, remote_path: str) -> None:
        return transfer.upload(self.sftp, local_path, remote_path)

    def download(self, remote_path: str, local_path: str) -> None:
        return transfer.download(self.sftp, remote_path, local_path)

    def _is_remote_dir(self, remote_path: str) -> bool:
        return helpers.is_remote_dir(self.sftp, remote_path)

    def _remote_glob(self, pattern: str) -> list:
        """
        Expand a shell-style glob pattern on the remote SFTP server and return a list
        of matching remote paths. Supports standard glob tokens: '*', '?', and character
        classes like '[a-z]'. Does not implement recursive '**'.
        """
        return helpers.remote_glob(self.sftp, pattern)

    def close(self) -> None:
        if self.sftp:
            self.sftp.close()
            self.sftp = None

    def delete_remote(self, remote_path: str) -> None:
        """
        Delete a file on the remote server via SFTP. Ignores if file does not exist.
        """
        return helpers.delete_remote(self.sftp, remote_path)

    def delete_local(self, local_path: str) -> None:
        """
        Delete a local file with progress feedback.
        """
        return helpers.delete_local(local_path)

    def _delete_remote_folder(self, remote_folder: str) -> None:
        """
        Recursively delete a folder on the remote server. Ignores if folder does not exist.
        """
        return helpers.delete_remote_folder(self.sftp, remote_folder)

    def _delete_local_folder(self, local_folder: str) -> None:
        """
        Recursively delete a local folder with progress.
        """
        return helpers.delete_local_folder(local_folder)

    def delete(self, path: str, remote: bool = True, local: bool = True) -> None:
        """
        Delete a file or folder both locally and/or remotely.
        If remote is True, delete on remote SFTP server.
        If local is True, delete on local filesystem.
        """
        return helpers.delete(self.sftp, path, remote=remote, local=local)
