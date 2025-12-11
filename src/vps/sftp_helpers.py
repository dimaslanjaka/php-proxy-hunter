from typing import Optional, List
import sys
import os
import stat
import shutil
import fnmatch
import posixpath
import paramiko


def print_upload_progress(filename: str, size: int, sent: int) -> None:
    percent = float(sent) / float(size) * 100 if size else 100
    sys.stdout.write(f"\r📤\tUploading {filename}: {percent:.2f}%")
    sys.stdout.flush()


def print_download_progress(filename: str, size: int, received: int) -> None:
    percent = float(received) / float(size) * 100 if size else 100
    sys.stdout.write(f"\r⬇️\tDownloading {filename}: {percent:.2f}%")
    sys.stdout.flush()


def is_remote_dir(sftp: Optional[paramiko.SFTPClient], remote_path: str) -> bool:
    if sftp is None:
        return False
    try:
        remote_stat = sftp.stat(remote_path)
        if remote_stat is not None:
            st_mode = getattr(remote_stat, "st_mode", None)
            if isinstance(st_mode, int):
                return stat.S_ISDIR(st_mode)
        return False
    except Exception:
        return False


def remote_glob(sftp: Optional[paramiko.SFTPClient], pattern: str) -> List[str]:
    if sftp is None:
        raise RuntimeError("SFTP client not initialized.")
    comps = pattern.split("/")
    if comps and comps[0] == "":
        start_prefix = "/"
        start_idx = 1
    else:
        start_prefix = "."
        start_idx = 0

    matches: List[str] = []

    def _recurse(prefix: str, idx: int) -> None:
        if idx >= len(comps):
            matches.append(prefix if prefix != "" else "/")
            return
        comp = comps[idx]
        if comp == "":
            next_prefix = prefix if prefix != "/" else "/"
            _recurse(next_prefix, idx + 1)
            return

        has_glob = any(ch in comp for ch in "*?[")
        if has_glob:
            try:
                entries = sftp.listdir_attr(prefix)
            except Exception:
                return
            for e in entries:
                name = e.filename
                if fnmatch.fnmatchcase(name, comp):
                    next_path = posixpath.join(prefix, name) if prefix != "." else name
                    _recurse(next_path, idx + 1)
        else:
            next_path = posixpath.join(prefix, comp) if prefix != "." else comp
            try:
                sftp.stat(next_path)
            except Exception:
                return
            _recurse(next_path, idx + 1)

    _recurse(start_prefix, start_idx)
    normalized = [
        (
            m
            if m.startswith("/") or m.startswith(".")
            else ("/" + m if pattern.startswith("/") else m)
        )
        for m in matches
    ]
    return normalized


def delete_remote(sftp: Optional[paramiko.SFTPClient], remote_path: str) -> None:
    if sftp is None:
        raise RuntimeError("SFTP client not initialized.")
    try:
        sftp.remove(remote_path)
        print(f"✅\tRemote file deleted: {remote_path}")
    except FileNotFoundError:
        print(f"⚠️\tRemote file not found: {remote_path}")
    except IOError as e:
        import errno

        if hasattr(e, "errno") and e.errno == errno.ENOENT:
            print(f"⚠️\tRemote file not found: {remote_path}")
        else:
            raise


def delete_remote_folder(
    sftp: Optional[paramiko.SFTPClient], remote_folder: str
) -> None:
    if sftp is None:
        raise RuntimeError("SFTP client not initialized.")
    try:
        entries = sftp.listdir_attr(remote_folder)
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
            delete_remote_folder(sftp, remote_path)
        else:
            delete_remote(sftp, remote_path)
    print(f"Deleting remote folder {remote_folder}...")
    try:
        sftp.rmdir(remote_folder)
        print(f"✅\tRemote folder deleted: {remote_folder}")
    except FileNotFoundError:
        print(f"⚠️\tRemote folder not found: {remote_folder}")
    except IOError as e:
        import errno

        if hasattr(e, "errno") and e.errno == errno.ENOENT:
            print(f"⚠️\tRemote folder not found: {remote_folder}")
        else:
            raise


def delete_local(local_path: str) -> None:
    if not os.path.exists(local_path):
        print(f"❌\tLocal file not found: {local_path}")
        return
    file_size = os.path.getsize(local_path)
    print(f"🗑️\tDeleting local file: {local_path} ({file_size} bytes)")
    if file_size > 1024 * 1024:
        deleted = 0
        chunk = 1024 * 1024
        while deleted < file_size:
            percent = min(100, (deleted / file_size) * 100)
            sys.stdout.write(f"\r🗑️\tDeleting: {percent:.2f}%")
            sys.stdout.flush()
            deleted += chunk
        sys.stdout.write("\r🗑️\tDeleting: 100.00%\n")
    os.remove(local_path)
    print("✅\tLocal file deleted.")


def delete_local_folder(local_folder: str) -> None:
    total_files = sum(len(files) for _, _, files in os.walk(local_folder))
    deleted = 0
    print(f"Deleting local folder: {local_folder}")
    for root, _, files in os.walk(local_folder):
        for file in files:
            file_path = os.path.join(root, file)
            delete_local(file_path)
            deleted += 1
            percent = (deleted / total_files) * 100 if total_files else 100
            sys.stdout.write(f"\r🗑️\tDeleting files: {percent:.2f}%")
            sys.stdout.flush()
    shutil.rmtree(local_folder)
    sys.stdout.write("\r🗑️\tDeleting files: 100.00%\n")
    print("✅\tLocal folder deleted.")


def delete(
    sftp: Optional[paramiko.SFTPClient],
    path: str,
    remote: bool = True,
    local: bool = True,
) -> None:
    """
    Delete a file or folder both locally and/or remotely.
    If `remote` is True, delete on remote SFTP server using provided `sftp` client.
    If `local` is True, delete on local filesystem.
    """
    if remote:
        if sftp:
            if is_remote_dir(sftp, path):
                delete_remote_folder(sftp, path)
            else:
                delete_remote(sftp, path)
        else:
            print("❌\tSFTP client not initialized. Cannot delete remote.")
    if local:
        if os.path.exists(path):
            if os.path.isdir(path):
                delete_local_folder(path)
            else:
                delete_local(path)
        else:
            print(f"❌\tLocal path not found: {path}")
