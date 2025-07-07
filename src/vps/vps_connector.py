from typing import Any, Dict
import paramiko
import os
import sys
import json
import stat
import shutil

# Add project root to sys.path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))


def load_sftp_config(config_path=".vscode/sftp.json"):
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


class VPSConnector:
    def __init__(self, host, port, username, password=None, key_path=None):
        self.host = host
        self.port = port
        self.username = username
        self.password = password
        self.key_path = key_path
        self.client = None
        self.sftp = None

    def connect(self):
        print(f"Connecting to {self.host}...\t")
        self.client = paramiko.SSHClient()
        self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

        if self.key_path:
            key = paramiko.RSAKey.from_private_key_file(self.key_path)
            self.client.connect(self.host, self.port, self.username, pkey=key)
        else:
            self.client.connect(
                self.host, self.port, self.username, password=self.password
            )

        self.sftp = self.client.open_sftp()
        print("🟢\tConnected.")

    def _print_progress(self, filename, size, sent):
        percent = float(sent) / float(size) * 100
        sys.stdout.write(f"\r📤\tUploading {filename}: {percent:.2f}%")
        sys.stdout.flush()

    def upload(self, local_path, remote_path):
        if not self.sftp:
            print("❌\tSFTP client not initialized. Cannot upload.")
            return

        if os.path.isdir(local_path):
            self._upload_folder(local_path, remote_path)
        else:
            upload_needed = True
            try:
                remote_stat = self.sftp.stat(remote_path)
                local_size = os.path.getsize(local_path)
                if remote_stat.st_size == local_size:
                    print(f"⏩\tSkipping upload: {local_path} (same size as remote)")
                    upload_needed = False
            except IOError:
                # Remote file does not exist, so upload is needed
                pass

            if upload_needed:
                print(f"📤\tUploading file: {local_path} → {remote_path}")
                file_size = os.path.getsize(local_path)
                self.sftp.put(
                    local_path,
                    remote_path,
                    callback=lambda sent, total=file_size, f=local_path: self._print_progress(
                        f, total, sent
                    ),
                )
                print("\n✅\tFile upload complete.")

    def _upload_folder(self, local_folder, remote_folder):
        if not self.sftp:
            print("❌\tSFTP client not initialized. Cannot upload.")
            return
        print(f"📁\tUploading folder: {local_folder} → {remote_folder}")
        for root, _, files in os.walk(local_folder):
            rel_path = os.path.relpath(root, local_folder)
            remote_path = os.path.join(remote_folder, rel_path).replace("\\", "/")

            # Create directory if it doesn't exist
            try:
                self.sftp.listdir(remote_path)
            except IOError:
                self.sftp.mkdir(remote_path)

            for file in files:
                local_file = os.path.join(root, file)
                remote_file = os.path.join(remote_path, file).replace("\\", "/")
                print(f" -\tUploading {local_file} → {remote_file}")
                file_size = os.path.getsize(local_file)
                self.sftp.put(
                    local_file,
                    remote_file,
                    callback=lambda sent, total=file_size, f=local_file: self._print_progress(
                        f, total, sent
                    ),
                )
                print()  # newline after progress bar

    def download(self, remote_path, local_path):
        if not self.sftp:
            print("❌\tSFTP client not initialized. Cannot download.")
            return
        print(f"⬇️\tDownloading {remote_path} to {local_path}...\t")
        self.sftp.get(remote_path, local_path)
        print("✅\tDownload complete.")

    def delete_remote(self, remote_path: str) -> None:
        """Delete a file on the remote server via SFTP. Ignores if file does not exist."""
        if not self.sftp:
            print("❌\tSFTP client not initialized. Cannot delete remote file.")
            return
        print(f"Deleting remote file {remote_path}...\t")
        try:
            self.sftp.remove(remote_path)
            print("✅\tRemote file deleted.")
        except FileNotFoundError:
            print(f"⚠️\tRemote file not found: {remote_path}")
        except IOError as e:
            import errno

            if hasattr(e, "errno") and e.errno == errno.ENOENT:
                print(f"⚠️\tRemote file not found: {remote_path}")
            else:
                raise

    def delete_local(self, local_path: str) -> None:
        """Delete a local file with progress feedback."""
        if not os.path.exists(local_path):
            print(f"❌\tLocal file not found: {local_path}")
            return
        file_size = os.path.getsize(local_path)
        print(f"🗑️\tDeleting local file: {local_path} ({file_size} bytes)\t")
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

    def run_command(self, command, cwd=None):
        if not self.client:
            print("❌\tSSH client not initialized. Cannot run command.")
            return

        if cwd:
            command = f"cd {cwd} && {command}"

        print(f"📁\tRunning remote command: {command}")
        stdin, stdout, stderr = self.client.exec_command(command)
        output = stdout.read().decode()
        error = stderr.read().decode()

        print("📥\tCommand output:")
        print(output)
        if error:
            print("⚠️\tCommand error:")
            print(error)

    def run_command_live(self, command, cwd=None):
        if not self.client:
            print("❌\tSSH client not initialized. Cannot run command.")
            return

        if cwd:
            command = f"cd {cwd} && {command}"

        print(f"📡\tExecuting: {command}")
        transport = self.client.get_transport()
        if not transport:
            print("❌\tSSH transport not initialized. Cannot run command.")
            return
        channel = transport.open_session()
        channel.get_pty()  # simulates terminal behavior
        channel.exec_command(command)

        try:
            while True:
                if channel.recv_ready():
                    output = channel.recv(1024).decode("utf-8")
                    print(output, end="")

                if channel.recv_stderr_ready():
                    error = channel.recv_stderr(1024).decode("utf-8")
                    print(error, end="")

                if channel.exit_status_ready():
                    break
        finally:
            exit_status = channel.recv_exit_status()
            print(f"\n🚪\tCommand exited with status {exit_status}")

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

    def _is_remote_dir(self, remote_path: str) -> bool:
        try:
            if self.sftp is None:
                return False
            remote_stat = self.sftp.stat(remote_path)
            if remote_stat is None or remote_stat.st_mode is None:
                return False
            return stat.S_ISDIR(remote_stat.st_mode)
        except Exception:
            return False

    def _delete_remote_folder(self, remote_folder: str) -> None:
        """Recursively delete a folder on the remote server. Ignores if folder does not exist."""
        if self.sftp is None:
            print("❌\tSFTP client not initialized. Cannot delete remote folder.")
            return
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
        print(f"Deleting remote folder {remote_folder}...\t")
        try:
            self.sftp.rmdir(remote_folder)
            print("✅\tRemote folder deleted.")
        except FileNotFoundError:
            print(f"⚠️\tRemote folder not found: {remote_folder}")
        except IOError as e:
            import errno

            if hasattr(e, "errno") and e.errno == errno.ENOENT:
                print(f"⚠️\tRemote folder not found: {remote_folder}")
            else:
                raise

    def _delete_local_folder(self, local_folder: str) -> None:
        """Recursively delete a local folder with progress."""
        import shutil

        total_files = sum(len(files) for _, _, files in os.walk(local_folder))
        deleted = 0
        print(f"Deleting local folder: {local_folder}\t")
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

    def close(self):
        if self.sftp:
            self.sftp.close()
        if self.client:
            self.client.close()
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
    )

    try:
        vps.connect()
        vps.run_command("uptime")
        # Ensure 'test.txt' exists before uploading
        if not os.path.exists("test.txt"):
            with open("test.txt", "w") as f:
                f.write("This is a test file for upload.\n")

        vps.upload("test.txt", f"{sftp_config['remote_path']}/test.txt")
        vps.download(f"{sftp_config['remote_path']}/test.txt", "downloaded_test.txt")

        # Test new delete function (delete both local and remote)
        # Re-upload for demonstration
        vps.upload("test.txt", f"{sftp_config['remote_path']}/test.txt")
        shutil.copy("test.txt", "test_copy.txt")
        vps.upload("test_copy.txt", f"{sftp_config['remote_path']}/test_copy.txt")
        print("\nTesting new delete function on test.txt (both local and remote):")
        vps.delete("test.txt", remote=True, local=True)
        print("\nTesting new delete function on test_copy.txt (both local and remote):")
        vps.delete("test_copy.txt", remote=True, local=True)
        print("\nTesting new delete function on downloaded_test.txt (local only):")
        vps.delete("downloaded_test.txt", remote=False, local=True)
    finally:
        vps.close()
