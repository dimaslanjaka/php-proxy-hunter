from typing import Optional, List
import os
import stat
import posixpath
import shutil
import fnmatch
import paramiko

from . import sftp_helpers as helpers


def _upload_folder(
    sftp: paramiko.SFTPClient, local_folder: str, remote_folder: str
) -> None:
    for root, _, files in os.walk(local_folder):
        rel_path = os.path.relpath(root, local_folder)
        remote_path = os.path.join(remote_folder, rel_path).replace("\\", "/")
        try:
            sftp.listdir(remote_path)
        except Exception:
            try:
                sftp.mkdir(remote_path)
            except Exception:
                pass
        for file in files:
            local_file = os.path.join(root, file)
            remote_file = os.path.join(remote_path, file).replace("\\", "/")
            file_size = os.path.getsize(local_file)
            # bind file to default arg to avoid late binding in lambda
            f_name = file
            sftp.put(
                local_file,
                remote_file,
                callback=lambda sent, total=file_size, f=f_name: helpers.print_upload_progress(
                    f, total, sent
                ),
            )
            print()


def upload(
    sftp: Optional[paramiko.SFTPClient], local_path: str, remote_path: str
) -> None:
    if sftp is None:
        raise RuntimeError("SFTP client not initialized.")
    if os.path.isdir(local_path):
        _upload_folder(sftp, local_path, remote_path)
    else:
        file_size = os.path.getsize(local_path)
        f = os.path.basename(local_path)
        sftp.put(
            local_path,
            remote_path,
            callback=lambda sent, total=file_size, f=f: helpers.print_upload_progress(
                f, total, sent
            ),
        )
        print()


def _download_dir_recursive(
    sftp: paramiko.SFTPClient, remote_path: str, local_path: str
) -> None:
    if not os.path.exists(local_path):
        os.makedirs(local_path, exist_ok=True)
    for entry in sftp.listdir_attr(remote_path):
        remote_item = os.path.join(remote_path, entry.filename).replace("\\", "/")
        local_item = os.path.join(local_path, entry.filename)
        if entry.st_mode is not None and stat.S_ISDIR(entry.st_mode):
            _download_dir_recursive(sftp, remote_item, local_item)
        else:
            file_size = getattr(entry, "st_size", None)
            if file_size:
                f_name = entry.filename
                sftp.get(
                    remote_item,
                    local_item,
                    callback=lambda received, total=file_size, f=f_name: helpers.print_download_progress(
                        f, total, received
                    ),
                )
                print()
            else:
                sftp.get(remote_item, local_item)


def download(
    sftp: Optional[paramiko.SFTPClient], remote_path: str, local_path: str
) -> None:
    if sftp is None:
        raise RuntimeError("SFTP client not initialized.")

    # handle glob patterns
    if any(ch in remote_path for ch in "*?["):
        matches = helpers.remote_glob(sftp, remote_path)
        if not matches:
            print(f"⚠️\tNo remote matches for pattern: {remote_path}")
            return
        multiple = len(matches) > 1
        base_dir = local_path if multiple or os.path.isdir(local_path) else local_path
        if multiple and not os.path.exists(base_dir):
            os.makedirs(base_dir, exist_ok=True)
        for m in matches:
            if helpers.is_remote_dir(sftp, m):
                target_local = (
                    os.path.join(base_dir, posixpath.basename(m))
                    if multiple or os.path.isdir(base_dir)
                    else base_dir
                )
                _download_dir_recursive(sftp, m, target_local)
            else:
                local_file = (
                    os.path.join(base_dir, posixpath.basename(m))
                    if os.path.isdir(base_dir) or multiple
                    else base_dir
                )
                try:
                    remote_stat = sftp.stat(m)
                    file_size = remote_stat.st_size
                except Exception:
                    file_size = None
                if file_size:
                    f = posixpath.basename(m)
                    sftp.get(
                        m,
                        local_file,
                        callback=lambda received, total=file_size, f=f: helpers.print_download_progress(
                            f, total, received
                        ),
                    )
                    print()
                else:
                    sftp.get(m, local_file)
        return

    if helpers.is_remote_dir(sftp, remote_path):
        if not os.path.exists(local_path):
            os.makedirs(local_path, exist_ok=True)
        _download_dir_recursive(sftp, remote_path, local_path)
    else:
        try:
            remote_stat = sftp.stat(remote_path)
            file_size = remote_stat.st_size
        except Exception:
            file_size = None
        if file_size:
            f = os.path.basename(remote_path)
            sftp.get(
                remote_path,
                local_path,
                callback=lambda received, total=file_size, f=f: helpers.print_download_progress(
                    f, total, received
                ),
            )
            print()
        else:
            sftp.get(remote_path, local_path)
