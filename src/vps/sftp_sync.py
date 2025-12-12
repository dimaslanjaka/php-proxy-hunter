from typing import Optional, Dict, Tuple
import os
import posixpath
import stat
import paramiko

from . import sftp_helpers as helpers
from . import sftp_transfer as transfer


def _walk_remote_files(
    sftp: paramiko.SFTPClient, remote_root: str
) -> Dict[str, Tuple[float, int]]:
    """Return a mapping of relative path -> (mtime, size) for files under remote_root.
    Paths are relative to `remote_root` and use OS-native separators for local comparison.
    """
    files: Dict[str, Tuple[float, int]] = {}

    def _recurse(prefix: str, base_rel: str = ""):
        try:
            entries = sftp.listdir_attr(prefix)
        except Exception:
            return
        for e in entries:
            name = e.filename
            remote_path = (
                posixpath.join(prefix, name) if prefix not in ("", ".") else name
            )
            rel = os.path.join(base_rel, name)
            if e.st_mode is not None and stat.S_ISDIR(e.st_mode):
                _recurse(remote_path, rel)
            else:
                mtime = getattr(e, "st_mtime", None) or getattr(e, "st_atime", 0)
                size = getattr(e, "st_size", 0)
                files[rel] = (float(mtime), int(size))

    # If remote_root is a directory, start recursion there; otherwise treat as a single file
    if helpers.is_remote_dir(sftp, remote_root):
        _recurse(remote_root, "")
    else:
        # single file: stat and add
        try:
            a = sftp.stat(remote_root)
            name = posixpath.basename(remote_root)
            files[name] = (
                float(getattr(a, "st_mtime", 0)),
                int(getattr(a, "st_size", 0)),
            )
        except Exception:
            pass

    return files


def sync_remote_to_local(
    sftp: Optional[paramiko.SFTPClient],
    remote_root: str,
    local_root: str,
    delete_extra: bool = False,
    compare: str = "size",
    dry_run: bool = False,
    time_tolerance: float = 1.0,
) -> None:
    """Sync remote -> local.

    Parameters
    - sftp: an active Paramiko SFTPClient.
    - remote_root: remote file or directory root on the server.
    - local_root: destination local directory.
    - delete_extra: if True, remove local files not present remotely.
    - compare: comparison mode controlling when files are downloaded:
        * "mtime": download when remote modification time is newer than local by more than `time_tolerance` seconds.
        * "size": download when the remote and local file sizes differ.
        * "mtime+size": download when either size differs or remote mtime is newer than local by more than `time_tolerance`.
        The default is "size".
    - dry_run: if True, only print planned actions without performing them.
    - time_tolerance: float number of seconds used to tolerate small mtime
        differences when using "mtime" or "mtime+size".
    """
    if sftp is None:
        raise RuntimeError("SFTP client not initialized.")

    # Normalize local root
    local_root = os.path.abspath(local_root)
    if not os.path.exists(local_root):
        if not dry_run:
            os.makedirs(local_root, exist_ok=True)
        else:
            print(f"DRY RUN: would create local root: {local_root}")

    remote_files = _walk_remote_files(sftp, remote_root)

    # Download or update files
    for rel, (r_mtime, r_size) in remote_files.items():
        local_path = os.path.join(local_root, rel)
        local_dir = os.path.dirname(local_path)
        if not os.path.exists(local_dir):
            if dry_run:
                print(f"DRY RUN: would create directory: {local_dir}")
            else:
                os.makedirs(local_dir, exist_ok=True)

        need_download = False
        reason = ""
        if not os.path.exists(local_path):
            need_download = True
            reason = "missing"
        else:
            if compare == "mtime":
                l_mtime = os.path.getmtime(local_path)
                if r_mtime > l_mtime + time_tolerance:
                    need_download = True
                    reason = f"remote newer (r:{r_mtime} > l:{l_mtime})"
            elif compare == "size":
                l_size = os.path.getsize(local_path)
                if r_size != l_size:
                    need_download = True
                    reason = f"size differs (r:{r_size} != l:{l_size})"
            elif compare == "mtime+size":
                l_mtime = os.path.getmtime(local_path)
                l_size = os.path.getsize(local_path)
                if r_size != l_size or r_mtime > l_mtime + time_tolerance:
                    need_download = True
                    reason = "mtime or size differs"
            else:
                raise ValueError("unsupported compare mode")

        remote_path = posixpath.join(remote_root, rel).replace("\\", "/")
        if need_download:
            if dry_run:
                print(
                    f"DRY RUN: would download {remote_path} -> {local_path} ({reason})"
                )
            else:
                print(f"Downloading {remote_path} -> {local_path} ({reason})")
                transfer.download(sftp, remote_path, local_path)

    # Optionally delete extra local files not present remotely
    if delete_extra:
        local_files = set()
        for root, _, files in os.walk(local_root):
            for f in files:
                full = os.path.join(root, f)
                rel = os.path.relpath(full, local_root)
                local_files.add(rel)

        remote_set = set(remote_files.keys())
        to_delete = local_files - remote_set
        for rel in sorted(to_delete):
            local_path = os.path.join(local_root, rel)
            if dry_run:
                print(f"DRY RUN: would delete local file: {local_path}")
            else:
                print(f"Deleting local file: {local_path}")
                helpers.delete_local(local_path)
