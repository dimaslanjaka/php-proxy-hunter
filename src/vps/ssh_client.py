from typing import Optional
import paramiko
import sys
import threading
import platform


class SSHClient:
    """
    Handles SSH connection and command execution.
    """

    client: Optional[paramiko.SSHClient]

    def __init__(
        self,
        host: str,
        port: int,
        username: str,
        password: Optional[str] = None,
        key_path: Optional[str] = None,
    ):
        """
        Initialize SSHClient with connection parameters.
        """
        self.host = host
        self.port = port
        self.username = username
        self.password = password
        self.key_path = key_path
        self.client = None

    def connect(self) -> None:
        """
        Establish SSH connection.
        """
        self.client = paramiko.SSHClient()
        self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        if self.key_path:
            key = paramiko.RSAKey.from_private_key_file(self.key_path)
            self.client.connect(self.host, self.port, self.username, pkey=key)
        else:
            self.client.connect(
                self.host, self.port, self.username, password=self.password
            )

    def run_command(self, command: str, cwd: Optional[str] = None) -> tuple[str, str]:
        """
        Run a command on the remote server. Optionally specify a working directory.
        Returns (stdout, stderr).
        """
        if not self.client:
            raise RuntimeError("SSH client not connected.")
        if cwd:
            command = f"cd {cwd} && {command}"
        stdin, stdout, stderr = self.client.exec_command(command)
        output = stdout.read().decode()
        error = stderr.read().decode()
        return output, error

    def _write_stdin_unix(self, channel):
        """Forward stdin to SSH channel on Unix-like systems."""
        import select

        try:
            while not channel.exit_status_ready():
                if select.select([sys.stdin], [], [], 0.1)[0]:
                    data = sys.stdin.readline()
                    if data:
                        channel.send(data.encode())
        except Exception:
            pass

    def _write_stdin_windows(self, channel):
        """Forward stdin to SSH channel on Windows systems."""
        import msvcrt

        try:
            while not channel.exit_status_ready():
                if msvcrt.kbhit():
                    ch = msvcrt.getwche()
                    if ch:
                        channel.send(ch.encode())
        except Exception:
            pass

    def run_command_live(self, command: str, cwd: Optional[str] = None) -> int:
        """
        Run a command on the remote server and stream output live to local stdout/stderr.
        Allows interactive input from the user (e.g., typing 'yes' and pressing Enter).
        Supports both Windows and Unix-like systems.
        Returns the exit status of the command.
        """
        if not self.client:
            raise RuntimeError("SSH client not connected.")
        if cwd:
            command = f"cd {cwd} && {command}"
        transport = self.client.get_transport()
        if transport is None:
            raise RuntimeError("SSH transport not available.")
        channel = transport.open_session()
        channel.get_pty()
        channel.exec_command(command)

        if platform.system() == "Windows":
            stdin_thread = threading.Thread(
                target=self._write_stdin_windows, args=(channel,), daemon=True
            )
        else:
            stdin_thread = threading.Thread(
                target=self._write_stdin_unix, args=(channel,), daemon=True
            )
        stdin_thread.start()
        try:
            while True:
                # Read all output until the channel is closed and all output is consumed
                if channel.recv_ready():
                    sys.stdout.write(channel.recv(4096).decode())
                    sys.stdout.flush()
                if channel.recv_stderr_ready():
                    sys.stderr.write(channel.recv_stderr(4096).decode())
                    sys.stderr.flush()
                if channel.exit_status_ready():
                    # Wait for all output to be consumed after exit
                    while channel.recv_ready() or channel.recv_stderr_ready():
                        if channel.recv_ready():
                            sys.stdout.write(channel.recv(4096).decode())
                            sys.stdout.flush()
                        if channel.recv_stderr_ready():
                            sys.stderr.write(channel.recv_stderr(4096).decode())
                            sys.stderr.flush()
                    break
        finally:
            print()  # Always print a newline after command completes
        stdin_thread.join(timeout=1)
        return channel.recv_exit_status()

    def close(self) -> None:
        """
        Close the SSH connection.
        """
        if self.client:
            self.client.close()
            self.client = None
