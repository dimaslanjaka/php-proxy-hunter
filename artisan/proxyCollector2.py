import os
import sys
import random
import signal

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from artisan.proxyCollector import get_added_proxy_files, LOCKS_DIR
from src.ProxyDB import ProxyDB
from src.shared import init_db
from proxy_hunter import extract_proxies
from src.utils.file.FileLockHelper import FileLockHelper

# Global lock reference for signal handler
_global_file_lock = None


def cleanup_and_exit(signum=None, frame=None):
    global _global_file_lock
    if _global_file_lock:
        try:
            _global_file_lock.release()
            print(f"\nLock released: {_global_file_lock.file_path}")
        except Exception as e:
            print(f"\nError releasing lock: {e}")
    sys.exit(0)


def collect():
    # Register signal handlers so locks are released on interrupt/terminate
    signal.signal(signal.SIGINT, cleanup_and_exit)
    signal.signal(signal.SIGTERM, cleanup_and_exit)

    files = get_added_proxy_files()
    if not files:
        print("No added proxy files found.")
        return

    # Filter files by size (only files smaller than 800 KB)
    max_size = 800 * 1024
    small_files = []
    for fp in files:
        try:
            if os.path.getsize(fp) < max_size:
                small_files.append(fp)
        except Exception:
            # skip files we can't stat
            continue

    if not small_files:
        print(f"No added proxy files under {max_size // 1024} KB found.")
        return

    # Choose the smallest file by size to reduce processing time
    try:
        selected_file = min(
            small_files,
            key=lambda p: os.path.getsize(p) if os.path.exists(p) else float("inf"),
        )
    except Exception:
        # Fallback to random choice if anything goes wrong
        selected_file = random.choice(small_files)
    print(f"Selected file: {selected_file}")

    lock_name = os.path.basename(selected_file) + ".lock"
    per_lock_path = os.path.join(LOCKS_DIR, lock_name)
    per_lock = FileLockHelper(per_lock_path)

    global _global_file_lock
    _global_file_lock = per_lock

    if not per_lock.lock():
        print(f"Skipping {selected_file}: locked by another process")
        _global_file_lock = None
        return

    try:
        db = init_db("mysql")
        with open(selected_file, "r", encoding="utf-8", errors="ignore") as f:
            for line in f:
                proxy = line.strip()
                if proxy:
                    extracted_proxies = extract_proxies(proxy)
                    if extracted_proxies:
                        for p in extracted_proxies:
                            if p.username and p.password:
                                print(
                                    f"Adding proxy: {p.proxy}@{p.username}:{p.password}"
                                )
                                db.add(f"{p.proxy}@{p.username}:{p.password}")
                            else:
                                print(f"Adding proxy: {p.proxy}")
                                db.add(p.proxy)
                    else:
                        print(f"No valid proxies found in line: {line.strip()}")
        db.close()

        # Delete the file after successful processing
        try:
            os.remove(selected_file)
            print(f"Deleted processed file: {selected_file}")
        except Exception as e:
            print(f"Failed to delete file {selected_file}: {e}")
    finally:
        try:
            per_lock.release()
            print(f"Lock released: {per_lock.file_path}")
        except Exception as e:
            print(f"Error releasing lock for {selected_file}: {e}")
        _global_file_lock = None


if __name__ == "__main__":
    collect()
