"""
File Lock Helper

Provides robust file-based locking for process synchronization.
Similar to PHP FileLockHelper but using platform-specific locking mechanisms.
"""

import os
import sys
from pathlib import Path

# Platform-specific imports
if sys.platform == "win32":
    import msvcrt
else:
    import fcntl


class FileLockHelper:
    """File-based lock helper for process synchronization."""

    def __init__(self, file_path: str):
        """
        Constructor.

        Args:
            file_path (str): Path to the lock file.
        """
        self.file_path = file_path
        self.handle = None
        self.lock_type = None

    def lock(self, lock_type: str = "exclusive") -> bool:
        """
        Acquire a lock.

        Args:
            lock_type (str): Use 'exclusive' for exclusive, 'shared' for shared lock.

        Returns:
            bool: True on success, False on failure.
        """
        self.handle = self._open_lock_handle()
        if self.handle is None:
            return False

        self.lock_type = lock_type

        try:
            # Use non-blocking lock so callers don't hang waiting for the lock
            # to be released by another process. Return False immediately if
            # the lock can't be acquired.
            if sys.platform == "win32":
                # Windows: Use msvcrt for file locking
                try:
                    msvcrt.locking(self.handle.fileno(), msvcrt.LK_NBLCK, 1)
                    return True
                except OSError:
                    self.handle.close()
                    self.handle = None
                    return False
            else:
                # Unix-like: Use fcntl for file locking
                lock_flag = fcntl.LOCK_EX if lock_type == "exclusive" else fcntl.LOCK_SH
                fcntl.flock(self.handle, lock_flag | fcntl.LOCK_NB)
                return True
        except (IOError, OSError):
            # Lock failed, close the handle
            if self.handle:
                self.handle.close()
                self.handle = None
            return False

    def unlock(self) -> None:
        """Unlock and close the file handle."""
        if self.handle is not None:
            try:
                if sys.platform == "win32":
                    # Windows: Unlock
                    try:
                        msvcrt.locking(self.handle.fileno(), msvcrt.LK_UNLCK, 1)
                    except OSError:
                        pass
                else:
                    # Unix-like: Unlock
                    fcntl.flock(self.handle, fcntl.LOCK_UN)
                self.handle.close()
            except (IOError, OSError):
                pass
            finally:
                self.handle = None

            # Delete the lock file gracefully
            try:
                if os.path.exists(self.file_path):
                    os.unlink(self.file_path)
            except (IOError, OSError):
                pass

    def release(self) -> None:
        """Release the lock (alias for unlock)."""
        self.unlock()

    def is_locked(self) -> bool:
        """
        Check if the lock file is currently locked by another process.

        Returns:
            bool: True if locked by another process, False otherwise.
        """
        # Attempt an exclusive non-blocking lock. This will fail if any other
        # process holds either a shared or exclusive lock on the file.
        handle = self._open_lock_handle()
        if handle is None:
            # Can't open the file — fall back to file existence check
            return os.path.exists(self.file_path)

        try:
            if sys.platform == "win32":
                try:
                    msvcrt.locking(handle.fileno(), msvcrt.LK_NBLCK, 1)
                    # We acquired the lock; release it immediately
                    msvcrt.locking(handle.fileno(), msvcrt.LK_UNLCK, 1)
                    handle.close()
                    return False
                except OSError:
                    # Lock is held by another process
                    handle.close()
                    return True
            else:
                fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
                # We acquired the exclusive lock; release it immediately
                fcntl.flock(handle, fcntl.LOCK_UN)
                handle.close()
                return False
        except (IOError, OSError):
            # Lock is held by another process
            try:
                handle.close()
            except (IOError, OSError):
                pass
            return True

    def _open_lock_handle(self):
        """
        Open the lock file handle, ensuring the directory exists and is writable.
        Returns a file handle on success or None on failure.

        Returns:
            file object or None
        """
        lock_dir = os.path.dirname(self.file_path)

        if lock_dir and not os.path.isdir(lock_dir):
            try:
                os.makedirs(lock_dir, mode=0o777, exist_ok=True)
            except (IOError, OSError):
                return None

        if lock_dir and not os.access(lock_dir, os.W_OK):
            return None

        try:
            handle = open(self.file_path, "a+")
            return handle
        except (IOError, OSError):
            return None

    def __del__(self):
        """Automatically unlock on destruction."""
        self.unlock()


# Only run when executed directly from CLI
if __name__ == "__main__":
    lock_file = os.path.join(os.path.dirname(__file__), "mylock.lock")
    lock = FileLockHelper(lock_file)

    if lock.lock("exclusive"):
        print(f"Lock acquired {lock.file_path}. Starting work...")

        # Simulate work loop with periodic output
        import time

        for i in range(1, 6):
            print(f"Working... step {i}")
            if lock.is_locked():
                print("The lock file is in use by another process.")
            else:
                print("The lock file is free.")
            print(f"Lock file exists: {os.path.exists(lock.file_path)}")
            time.sleep(1)

        lock.unlock()
        print("Lock released")
    else:
        print(f"Could not acquire lock on {lock_file}")
