import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../")))

from src.vps.config import load_sftp_config  # noqa: E402
from src.vps.sftp_client import SFTPClient  # noqa: E402
from src.vps.ssh_client import SSHClient  # noqa: E402
import time
import tempfile
import argparse
import math
from src.func import get_relative_path


CHUNK = 4 * 1024 * 1024


def make_temp_file(path: str, size_bytes: int) -> None:
    """Create a local file at `path` with the given size (writes zero bytes)."""
    written = 0
    with open(path, "wb") as f:
        while written < size_bytes:
            to_write = min(CHUNK, size_bytes - written)
            f.write(b"\0" * to_write)
            written += to_write


def measure_download(sftp: SFTPClient, remote_path: str, local_path: str) -> dict:
    """Download remote_path into local_path and return metrics."""
    start = time.time()
    sftp.download(remote_path, local_path)
    end = time.time()
    size = os.path.getsize(local_path)
    elapsed = max(end - start, 1e-9)
    bps = size / elapsed
    mbps = bps * 8 / 1_000_000
    return {"bytes": size, "seconds": elapsed, "bps": bps, "mbps": mbps}


def measure_upload(sftp: SFTPClient, local_path: str, remote_path: str) -> dict:
    """Upload local_path to remote_path and return metrics."""
    start = time.time()
    sftp.upload(local_path, remote_path)
    end = time.time()
    size = os.path.getsize(local_path)
    elapsed = max(end - start, 1e-9)
    bps = size / elapsed
    mbps = bps * 8 / 1_000_000
    return {"bytes": size, "seconds": elapsed, "bps": bps, "mbps": mbps}


def posix_join(a: str, b: str) -> str:
    if a.endswith("/"):
        return a + b
    return a + "/" + b


def run_speed_test(size_mb: int = 10, remote_file: str | None = None) -> int:
    cfg = load_sftp_config()
    host = cfg["host"]
    port = cfg.get("port", 22)
    username = cfg.get("username")
    password = cfg.get("password")
    key_path = cfg.get("key_path")
    remote_root = cfg.get("remote_path", "/")

    if not username:
        print("Username is required in SFTP config", file=sys.stderr)
        return 1

    ssh = SSHClient(host, port, username, password, key_path)
    ssh.connect()

    if not ssh.ssh_client:
        print("Failed to establish SSH connection", file=sys.stderr)
        return 1

    sftp = SFTPClient(ssh.ssh_client)

    size_bytes = int(size_mb * 1024 * 1024)

    # Prepare fixed local file under tmp/temp-file.bin (create tmp/ if needed)
    tmp_dir = os.path.dirname(get_relative_path("tmp", "temp-file.bin"))
    os.makedirs(tmp_dir, exist_ok=True)
    local_temp_path = get_relative_path("tmp", "temp-file.bin")
    make_temp_file(local_temp_path, size_bytes)
    # prepare guards for cleanup in case of early exceptions
    remote_path_full = None
    local_download_path = None
    try:
        if remote_file:
            remote_path_full = remote_file
        else:
            remote_path_full = posix_join(remote_root, f"speed_test_{size_mb}MB.bin")

        # upload to remote (measure upload speed)
        upload_metrics = measure_upload(sftp, local_temp_path, remote_path_full)

        # download to measure into fixed download file
        local_download_path = get_relative_path("tmp", "temp-file-down.bin")
        metrics = measure_download(sftp, remote_path_full, local_download_path)
        print(
            f"Uploaded   {upload_metrics['bytes']} bytes in {upload_metrics['seconds']:.3f}s -> {upload_metrics['mbps']:.2f} Mbps"
        )
        print(
            f"Downloaded {metrics['bytes']} bytes in {metrics['seconds']:.3f}s -> {metrics['mbps']:.2f} Mbps"
        )
    finally:
        # always remove download file if created
        if local_download_path and os.path.exists(local_download_path):
            try:
                os.remove(local_download_path)
            except Exception:
                pass

        # always cleanup remote test file if we created one
        if remote_path_full:
            try:
                sftp.delete(remote_path_full, remote=True, local=False)
            except Exception:
                pass

        # always remove local temp file
        if os.path.exists(local_temp_path):
            try:
                os.remove(local_temp_path)
            except Exception:
                pass
        sftp.close()
        ssh.close()

    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="SFTP download speed test from VPS")
    parser.add_argument("--size-mb", type=int, default=10, help="Test file size in MiB")
    parser.add_argument(
        "--remote-file",
        type=str,
        default=None,
        help="Remote file path to use instead of creating one",
    )
    args = parser.parse_args()
    return run_speed_test(size_mb=args.size_mb, remote_file=args.remote_file)


if __name__ == "__main__":
    raise SystemExit(main())
