import ctypes
import os
import shutil
import stat
import time
import platform


def delete_path(path: str) -> None:
    """
    Delete a folder or file specified by the path if it exists.

    Args:
        path (str): The path of the folder or file to delete.
    """

    # Use safe_delete for robust removal (retries, chmod, schedule on reboot)
    def _make_writable_tree(target: str) -> None:
        # Make target and its children writable where possible
        try:
            os.chmod(target, stat.S_IWRITE)
        except Exception:
            pass
        if os.path.isdir(target):
            for root, dirs, files in os.walk(target):
                for d in dirs:
                    try:
                        os.chmod(os.path.join(root, d), stat.S_IWRITE)
                    except Exception:
                        pass
                for f in files:
                    try:
                        os.chmod(os.path.join(root, f), stat.S_IWRITE)
                    except Exception:
                        pass

    def safe_delete(target: str, retries: int = 5, delay: float = 0.2) -> bool:
        if not os.path.exists(target):
            # Nothing to do
            return True

        # quick attempt
        try:
            if os.path.isdir(target):
                shutil.rmtree(target)
            else:
                os.remove(target)
            return True
        except FileNotFoundError:
            return True
        except PermissionError as e:
            # will retry below
            pass
        except OSError:
            # will retry below
            pass

        # try making writable and retry a few times
        for i in range(retries):
            try:
                _make_writable_tree(target)
                time.sleep(delay)
                if os.path.isdir(target):
                    shutil.rmtree(target)
                else:
                    os.remove(target)
                return True
            except FileNotFoundError:
                return True
            except PermissionError:
                continue
            except OSError:
                continue

        # if still locked on Windows, schedule deletion on reboot
        if platform.system().lower().startswith("win"):
            try:
                MOVEFILE_DELAY_UNTIL_REBOOT = 0x4
                res = ctypes.windll.kernel32.MoveFileExW(
                    ctypes.c_wchar_p(target), None, MOVEFILE_DELAY_UNTIL_REBOOT
                )
                if res:
                    print(f"Scheduled {target} for deletion on next reboot.")
                    return True
                else:
                    err = ctypes.GetLastError()
                    print(f"Failed to schedule deletion for {target}, WinErr={err}")
            except Exception as ex:
                print(f"Error scheduling deletion on reboot for {target}: {ex}")

        print(f"Could not delete path: {target}")
        return False

    # perform safe delete and print friendly messages
    if not os.path.exists(path):
        print(f"Path '{path}' does not exist.")
        return

    success = safe_delete(path)
    if success:
        print(f"Deleted path '{path}' successfully.")
    else:
        print(f"Failed to delete path '{path}'.")


def delete_path_if_exists(path):
    """
    Delete a file or folder if it exists.

    Parameters:
        path (str): The path to the file or folder to be deleted.

    Returns:
        None

    Raises:
        None
    """
    try:
        if os.path.exists(path):
            if os.path.isfile(path):
                os.remove(path)
                print(f"File '{path}' deleted.")
            elif os.path.isdir(path):
                shutil.rmtree(path)
                print(f"Folder '{path}' and its contents deleted.")
        else:
            print(f"'{path}' does not exist.")
    except PermissionError as e:
        print(f"Permission error: {e}")
