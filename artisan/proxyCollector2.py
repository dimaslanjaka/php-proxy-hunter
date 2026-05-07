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
from src.utils.parse_args import parse_args

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
            sz = os.path.getsize(fp)
            # skip zero-length files (likely empty placeholders) and very large files
            if sz == 0:
                try:
                    os.remove(fp)
                    print(f"Removed empty file: {fp}")
                except Exception:
                    print(f"Skipping empty file (cannot remove): {fp}")
                continue
            if sz < max_size:
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
    args = parse_args()
    file_lock_arg = getattr(args, "file_lock", None)
    if file_lock_arg:
        per_lock_path = file_lock_arg
    else:
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
        # Diagnostic: file existence and size
        try:
            print(f"File exists: {os.path.exists(selected_file)}")
            print(f"File size: {os.path.getsize(selected_file)} bytes")
            with open(selected_file, "rb") as fb:
                preview = fb.read(512)
            try:
                preview_text = preview.decode("utf-8", errors="replace")
            except Exception:
                preview_text = repr(preview)
            print(f"File preview (first 512 bytes): {preview_text}")
        except Exception as e:
            print(f"Could not stat/read selected file: {e}")
        added_count = 0
        skipped_count = 0
        proxies_extracted_total = 0
        sample_extracted = []

        # Read entire file and extract proxies from full content
        try:
            with open(selected_file, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()
        except Exception as e:
            print(f"Failed to read file {selected_file}: {e}")
            content = ""

        if not content or not content.strip():
            print("File content empty after reading")
        else:
            try:
                extracted_proxies = extract_proxies(content)
            except Exception as e:
                print(f"extract_proxies failed: {e}")
                extracted_proxies = []

            proxies_extracted_total = len(extracted_proxies)
            for p in extracted_proxies:
                if p.username and p.password:
                    proxy_str = f"{p.proxy}@{p.username}:{p.password}"
                else:
                    proxy_str = p.proxy

                if len(sample_extracted) < 10:
                    sample_extracted.append(proxy_str)

                try:
                    sel = db.select(proxy_str)
                    exists = bool(sel)
                except Exception as e:
                    print(f"DB select failed for {proxy_str}: {e}")
                    exists = False

                if exists:
                    print(f"Skipping existing proxy: {proxy_str}")
                    skipped_count += 1
                else:
                    print(f"Adding proxy: {proxy_str}")
                    try:
                        db.add(proxy_str)
                        added_count += 1
                    except Exception as e:
                        print(f"Failed to add proxy {proxy_str}: {e}")
        db.close()

        print(f"Extracted proxies: {proxies_extracted_total}")
        print(f"Skipped (already present): {skipped_count}")
        if sample_extracted:
            print(f"Sample extracted proxies: {sample_extracted}")
        print(f"Added {added_count} proxies to database")

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
