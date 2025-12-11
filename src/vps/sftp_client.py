from typing import Optional
import paramiko
import os
import stat
import posixpath
from . import sftp_helpers as helpers


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
        if self.sftp is None:
            raise RuntimeError("SFTP client not initialized.")
        if os.path.isdir(local_path):
            self._upload_folder(local_path, remote_path)
        else:
            file_size = os.path.getsize(local_path)
            self.sftp.put(
                local_path,
                remote_path,
                callback=lambda sent, total=file_size, f=os.path.basename(
                    local_path
                ): self._print_upload_progress(f, total, sent),
            )
            print()  # newline after progress bar

    def _upload_folder(self, local_folder: str, remote_folder: str) -> None:
        if self.sftp is None:
            raise RuntimeError("SFTP client not initialized.")
        for root, _, files in os.walk(local_folder):
            rel_path = os.path.relpath(root, local_folder)
            remote_path = os.path.join(remote_folder, rel_path).replace("\\", "/")
            try:
                self.sftp.listdir(remote_path)
            except IOError:
                self.sftp.mkdir(remote_path)
            for file in files:
                local_file = os.path.join(root, file)
                remote_file = os.path.join(remote_path, file).replace("\\", "/")
                file_size = os.path.getsize(local_file)
                self.sftp.put(
                    local_file,
                    remote_file,
                    callback=lambda sent, total=file_size, f=file: self._print_upload_progress(
                        f, total, sent
                    ),
                )
                print()  # newline after progress bar

    def download(self, remote_path: str, local_path: str) -> None:
        if self.sftp is None:
            raise RuntimeError("SFTP client not initialized.")
        # If remote_path contains glob characters, expand them and download each match.
        if any(ch in remote_path for ch in "*?["):
            matches = self._remote_glob(remote_path)
            if not matches:
                print(f"⚠️\tNo remote matches for pattern: {remote_path}")
                return
            # If multiple matches or local_path is a directory, place matches inside local_path directory
            multiple = len(matches) > 1
            if multiple:
                if not os.path.exists(local_path):
                    os.makedirs(local_path, exist_ok=True)
                base_dir = local_path
            else:
                # single match: behave like original download (allow file target)
                base_dir = local_path

            for m in matches:
                # For each match, if it's a directory, download recursively into a subfolder
                if self._is_remote_dir(m):
                    target_local = (
                        os.path.join(base_dir, posixpath.basename(m))
                        if multiple or os.path.isdir(base_dir)
                        else base_dir
                    )
                    if not os.path.exists(target_local):
                        os.makedirs(target_local, exist_ok=True)
                    self.download(m, target_local)
                else:
                    # file: determine local filename
                    if os.path.isdir(base_dir) or multiple:
                        local_file = os.path.join(base_dir, posixpath.basename(m))
                    else:
                        local_file = base_dir
                    try:
                        remote_stat = self.sftp.stat(m)
                        file_size = remote_stat.st_size
                    except Exception:
                        file_size = None
                    if file_size:
                        self.sftp.get(
                            m,
                            local_file,
                            callback=lambda received, total=file_size, f=posixpath.basename(
                                m
                            ): self._print_download_progress(
                                f, total, received
                            ),
                        )
                        print()
                    else:
                        self.sftp.get(m, local_file)
            return
        if self._is_remote_dir(remote_path):
            if not os.path.exists(local_path):
                os.makedirs(local_path)
            for entry in self.sftp.listdir_attr(remote_path):
                remote_item = os.path.join(remote_path, entry.filename).replace(
                    "\\", "/"
                )
                local_item = os.path.join(local_path, entry.filename)
                if entry.st_mode is not None and stat.S_ISDIR(entry.st_mode):
                    self.download(remote_item, local_item)
                else:
                    file_size = entry.st_size if hasattr(entry, "st_size") else None
                    if file_size:
                        self.sftp.get(
                            remote_item,
                            local_item,
                            callback=lambda received, total=file_size, f=entry.filename: self._print_download_progress(
                                f, total, received
                            ),
                        )
                        print()  # newline after progress bar
                    else:
                        self.sftp.get(remote_item, local_item)
        else:
            try:
                remote_stat = self.sftp.stat(remote_path)
                file_size = remote_stat.st_size
            except Exception:
                file_size = None
            if file_size:
                self.sftp.get(
                    remote_path,
                    local_path,
                    callback=lambda received, total=file_size, f=os.path.basename(
                        remote_path
                    ): self._print_download_progress(f, total, received),
                )
                print()  # newline after progress bar
            else:
                self.sftp.get(remote_path, local_path)

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
        if remote:
            if self.sftp:
                if self._is_remote_dir(path):
                    self._delete_remote_folder(path)
                else:
                    self.delete_remote(path)
            else:
                print("❌\tSFTP client not initialized. Cannot delete remote.")
        if local:
            if os.path.exists(path):
                if os.path.isdir(path):
                    self._delete_local_folder(path)
                else:
                    self.delete_local(path)
            else:
                print(f"❌\tLocal path not found: {path}")
