"""Tailscale helper utilities.

Provides a function to run `tailscale status --json` and save the
output to a repository-relative path, using `get_relative_path(...)`
when available, otherwise falling back to a sensible default.
"""

from __future__ import annotations

import json
import subprocess
import os
import sys
from pathlib import Path
import ipaddress
from typing import Any, Dict, Optional, Union
import posixpath
import paramiko

from src.vps.sftp_transfer import upload as sftp_upload

# ensure repo root is importable (match pattern used in mysql-test.py)
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.insert(0, ROOT)

from src.func import get_relative_path
from proxy_hunter import write_file


def save_tailscale_status(
    local_path: Optional[Union[str, Path]] = None, tailscale_cmd: str = "tailscale"
) -> Path:
    """Run `tailscale status --json` and write result to a file.

    If `local_path` is None the function will use `get_relative_path('tmp/data/tailscale.json')`.

    Returns the `Path` to which the JSON was written.
    Raises RuntimeError on failures.
    """

    # Resolve destination path
    if local_path is None:
        dest_path = Path(get_relative_path("tmp/data/tailscale.json"))
    else:
        dest_path = Path(local_path)

    dest_path.parent.mkdir(parents=True, exist_ok=True)

    cmd = [tailscale_cmd, "status", "--json"]
    try:
        proc = subprocess.run(
            cmd, check=True, capture_output=True, text=True, timeout=30
        )
    except FileNotFoundError as exc:
        raise RuntimeError("tailscale binary not found on PATH") from exc
    except subprocess.CalledProcessError as exc:
        out = exc.stdout or exc.stderr or ""
        raise RuntimeError(f"tailscale status failed: {out}") from exc

    try:
        data: Dict[str, Any] = json.loads(proc.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError("failed to decode tailscale --json output") from exc

    write_file(str(dest_path), json.dumps(data, indent=2))
    return dest_path


def get_tailscale_status(tailscale_cmd: str = "tailscale") -> Dict[str, Any]:
    """Return parsed JSON from `tailscale status --json` without writing."""
    cmd = [tailscale_cmd, "status", "--json"]
    try:
        proc = subprocess.run(
            cmd, check=True, capture_output=True, text=True, timeout=30
        )
    except FileNotFoundError as exc:
        raise RuntimeError("tailscale binary not found on PATH") from exc
    except subprocess.CalledProcessError as exc:
        out = exc.stdout or exc.stderr or ""
        raise RuntimeError(f"tailscale status failed: {out}") from exc

    try:
        return json.loads(proc.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError("failed to decode tailscale --json output") from exc


def get_tailscale_ipv4(
    local_path: Optional[Union[str, Path]] = None, tailscale_cmd: str = "tailscale"
) -> str:
    """Return the first IPv4 address from `tailscale status --json`.

    If `local_path` is provided it is treated as a path to a saved JSON file
    (created by `save_tailscale_status`). Otherwise the function calls
    `get_tailscale_status()` to obtain live data.

    Raises RuntimeError if no IPv4 address is found.
    """
    if local_path:
        path = Path(local_path)
        if not path.exists():
            raise RuntimeError(f"tailscale status file not found: {path}")
        data = json.loads(path.read_text())
    else:
        data = get_tailscale_status(tailscale_cmd=tailscale_cmd)

    candidates = []
    if isinstance(data.get("TailscaleIPs"), list):
        candidates.extend(data.get("TailscaleIPs", []))
    self = data.get("Self") if isinstance(data.get("Self"), dict) else None
    if self and isinstance(self.get("TailscaleIPs"), list):
        candidates.extend(self.get("TailscaleIPs", []))

    for ip in candidates:
        try:
            if ipaddress.ip_address(ip).version == 4:
                return ip
        except Exception:
            continue

    raise RuntimeError("no IPv4 address found in tailscale status")


def upload_tailscale_status(
    local_path: Optional[Union[str, Path]] = None,
    remote_filename: str = "tailscale.json",
    port: int = 22,
) -> bool:
    """Upload the saved or live tailscale status JSON to the configured SFTP server.

    Reads `SFTP_HOST`, `SFTP_USER`, `SFTP_PASS`, and `SFTP_PATH` from environment.
    If `local_path` is not provided the function will call `save_tailscale_status()` to
    create `tmp/data/tailscale.json` and upload that file.

    Returns True on success. Raises on failure.
    """
    # ensure local file exists
    if local_path is None:
        local_path = get_relative_path("tmp/data/tailscale.json")
        local_path = Path(local_path)
        if not local_path.exists():
            save_tailscale_status(str(local_path))
    else:
        local_path = Path(local_path)
        if not local_path.exists():
            raise RuntimeError(f"local status file not found: {local_path}")

    host = os.getenv("SFTP_HOST")
    user = os.getenv("SFTP_USER")
    passwd = os.getenv("SFTP_PASS")
    remote_dir = os.getenv("SFTP_PATH", "/var/www/html")
    remote_path = posixpath.join(remote_dir, "tmp", "data", remote_filename)

    # allow empty password (for key-based auth or empty-password accounts)
    if not host or not user:
        raise RuntimeError(
            "SFTP credentials missing in environment (SFTP_HOST/SFTP_USER)"
        )

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        ssh.connect(hostname=host, port=port, username=user, password=passwd)
        sftp = ssh.open_sftp()
        sftp_upload(sftp, str(local_path), remote_path)
        sftp.close()
        ssh.close()
    except Exception:
        try:
            ssh.close()
        except Exception:
            pass
        raise

    return True
