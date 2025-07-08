from typing import Optional
import paramiko


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

    def run_command_live(self, command: str, cwd: Optional[str] = None) -> int:
        """
        Run a command on the remote server and stream output live to local stdout/stderr.
        Returns the exit status of the command.
        """
        import sys

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
        while True:
            if channel.recv_ready():
                sys.stdout.write(channel.recv(4096).decode())
                sys.stdout.flush()
            if channel.recv_stderr_ready():
                sys.stderr.write(channel.recv_stderr(4096).decode())
                sys.stderr.flush()
            if channel.exit_status_ready():
                break
        return channel.recv_exit_status()

    def close(self) -> None:
        """
        Close the SSH connection.
        """
        if self.client:
            self.client.close()
            self.client = None
