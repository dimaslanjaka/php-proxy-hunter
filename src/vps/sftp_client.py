from typing import Optional
import paramiko
import os
import stat
import sys
import shutil


class SFTPClient:
    """Handles SFTP upload, download, and file/folder operations."""

    sftp: Optional[paramiko.SFTPClient]

    def __init__(self, ssh_client: paramiko.SSHClient):
        """
        Initialize SFTPClient with a connected paramiko.SSHClient.
        """
        self.sftp: Optional[paramiko.SFTPClient] = ssh_client.open_sftp()

    def _print_upload_progress(self, filename: str, size: int, sent: int) -> None:
        percent = float(sent) / float(size) * 100 if size else 100
        sys.stdout.write(f"\r📤\tUploading {filename}: {percent:.2f}%")
        sys.stdout.flush()

    def _print_download_progress(self, filename: str, size: int, received: int) -> None:
        percent = float(received) / float(size) * 100 if size else 100
        sys.stdout.write(f"\r⬇️\tDownloading {filename}: {percent:.2f}%")
        sys.stdout.flush()

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
        if self.sftp is None:
            return False
        try:
            remote_stat = self.sftp.stat(remote_path)
            if remote_stat is not None and remote_stat.st_mode is not None:
                return stat.S_ISDIR(remote_stat.st_mode)
            return False
        except Exception:
            return False

    def close(self) -> None:
        if self.sftp:
            self.sftp.close()
            self.sftp = None

    def delete_remote(self, remote_path: str) -> None:
        """
        Delete a file on the remote server via SFTP. Ignores if file does not exist.
        """
        if self.sftp is None:
            raise RuntimeError("SFTP client not initialized.")
        try:
            self.sftp.remove(remote_path)
            print(f"✅\tRemote file deleted: {remote_path}")
        except FileNotFoundError:
            print(f"⚠️\tRemote file not found: {remote_path}")
        except IOError as e:
            import errno

            if hasattr(e, "errno") and e.errno == errno.ENOENT:
                print(f"⚠️\tRemote file not found: {remote_path}")
            else:
                raise

    def delete_local(self, local_path: str) -> None:
        """
        Delete a local file with progress feedback.
        """
        if not os.path.exists(local_path):
            print(f"❌\tLocal file not found: {local_path}")
            return
        file_size = os.path.getsize(local_path)
        print(f"🗑️\tDeleting local file: {local_path} ({file_size} bytes)")
        # Simulate progress for large files
        if file_size > 1024 * 1024:  # >1MB, show progress
            deleted = 0
            chunk = 1024 * 1024  # 1MB
            while deleted < file_size:
                percent = min(100, (deleted / file_size) * 100)
                sys.stdout.write(f"\r🗑️\tDeleting: {percent:.2f}%")
                sys.stdout.flush()
                deleted += chunk
            sys.stdout.write("\r🗑️\tDeleting: 100.00%\n")
        os.remove(local_path)
        print("✅\tLocal file deleted.")

    def _delete_remote_folder(self, remote_folder: str) -> None:
        """
        Recursively delete a folder on the remote server. Ignores if folder does not exist.
        """
        if self.sftp is None:
            raise RuntimeError("SFTP client not initialized.")
        try:
            entries = self.sftp.listdir_attr(remote_folder)
        except FileNotFoundError:
            print(f"⚠️\tRemote folder not found: {remote_folder}")
            return
        except IOError as e:
            import errno

            if hasattr(e, "errno") and e.errno == errno.ENOENT:
                print(f"⚠️\tRemote folder not found: {remote_folder}")
                return
            else:
                raise
        for entry in entries:
            remote_path = os.path.join(remote_folder, entry.filename).replace("\\", "/")
            if entry.st_mode is not None and stat.S_ISDIR(entry.st_mode):
                self._delete_remote_folder(remote_path)
            else:
                self.delete_remote(remote_path)
        print(f"Deleting remote folder {remote_folder}...")
        try:
            self.sftp.rmdir(remote_folder)
            print(f"✅\tRemote folder deleted: {remote_folder}")
        except FileNotFoundError:
            print(f"⚠️\tRemote folder not found: {remote_folder}")
        except IOError as e:
            import errno

            if hasattr(e, "errno") and e.errno == errno.ENOENT:
                print(f"⚠️\tRemote folder not found: {remote_folder}")
            else:
                raise

    def _delete_local_folder(self, local_folder: str) -> None:
        """
        Recursively delete a local folder with progress.
        """
        total_files = sum(len(files) for _, _, files in os.walk(local_folder))
        deleted = 0
        print(f"Deleting local folder: {local_folder}")
        for root, _, files in os.walk(local_folder):
            for file in files:
                file_path = os.path.join(root, file)
                self.delete_local(file_path)
                deleted += 1
                percent = (deleted / total_files) * 100 if total_files else 100
                sys.stdout.write(f"\r🗑️\tDeleting files: {percent:.2f}%")
                sys.stdout.flush()
        shutil.rmtree(local_folder)
        sys.stdout.write("\r🗑️\tDeleting files: 100.00%\n")
        print("✅\tLocal folder deleted.")

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
