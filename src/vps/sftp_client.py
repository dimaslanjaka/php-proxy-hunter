from typing import Optional, Union, List
import paramiko
from . import sftp_helpers as helpers
from . import sftp_transfer as transfer
from . import sftp_sync as sync


class SFTPClient:
    """Handles SFTP upload, download, and file/folder operations."""

    sftp_client: Optional[paramiko.SFTPClient]

    def __init__(self, ssh_client: paramiko.SSHClient):
        """
        Initialize SFTPClient with a connected paramiko.SSHClient.
        """
        self.sftp_client: Optional[paramiko.SFTPClient] = ssh_client.open_sftp()

    def _print_upload_progress(self, filename: str, size: int, sent: int) -> None:
        return helpers.print_upload_progress(filename, size, sent)

    def _print_download_progress(self, filename: str, size: int, received: int) -> None:
        return helpers.print_download_progress(filename, size, received)

    def upload(self, local_path: str, remote_path: str) -> None:
        return transfer.upload(self.sftp_client, local_path, remote_path)

    def download(self, remote_path: str, local_path: str) -> None:
        return transfer.download(self.sftp_client, remote_path, local_path)

    def _is_remote_dir(self, remote_path: str) -> bool:
        return helpers.is_remote_dir(self.sftp_client, remote_path)

    def _remote_glob(self, pattern: str) -> list:
        """
        Expand a shell-style glob pattern on the remote SFTP server and return a list
        of matching remote paths. Supports standard glob tokens: '*', '?', and character
        classes like '[a-z]'. Does not implement recursive '**'.
        """
        return helpers.remote_glob(self.sftp_client, pattern)

    def close(self) -> None:
        if self.sftp_client:
            self.sftp_client.close()
            self.sftp_client = None

    def delete_remote(self, remote_path: str) -> None:
        """
        Delete a file on the remote server via SFTP. Ignores if file does not exist.
        """
        return helpers.delete_remote(self.sftp_client, remote_path)

    def delete_local(self, local_path: str) -> None:
        """
        Delete a local file with progress feedback.
        """
        return helpers.delete_local(local_path)

    def _delete_remote_folder(self, remote_folder: str) -> None:
        """
        Recursively delete a folder on the remote server. Ignores if folder does not exist.
        """
        return helpers.delete_remote_folder(self.sftp_client, remote_folder)

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
        return helpers.delete(self.sftp_client, path, remote=remote, local=local)

    def sync_remote_to_local(
        self,
        remote_root: str,
        local_root: str,
        delete_extra: bool = False,
        exclude: Optional[Union[str, List[str]]] = None,
        compare: str = "mtime",
        dry_run: bool = False,
        time_tolerance: float = 1.0,
    ) -> None:
        """Sync a remote path into the given local directory.

        Delegates to `src.vps.sftp_sync.sync_remote_to_local`.
        """
        return sync.sync_remote_to_local(
            self.sftp_client,
            remote_root,
            local_root,
            delete_extra=delete_extra,
            exclude=exclude,
            compare=compare,
            dry_run=dry_run,
            time_tolerance=time_tolerance,
        )
