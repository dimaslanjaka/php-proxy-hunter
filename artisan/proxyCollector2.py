import os
import sys
import random
import signal
from typing import List, Tuple

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from artisan.proxyCollector import get_added_proxy_files, LOCKS_DIR
from src.ProxyDB import ProxyDB
from src.shared import init_db
from proxy_hunter import extract_proxies
from src.utils.file.FileLockHelper import FileLockHelper
from src.utils.parse_args import parse_args
from src.utils.file.remove_string_from_file import remove_string_from_file

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

    # Filter files by size (only files smaller than 800 KB) and cache sizes to
    # avoid repeated os.path.getsize calls. Remove zero-length files when safe.
    max_size = 800 * 1024
    small_file_tuples: List[Tuple[str, int]] = []  # list of (path, size)
    for fp in files:
        try:
            stat = os.stat(fp)
            sz = stat.st_size
            if sz == 0:
                try:
                    os.remove(fp)
                    print(f"Removed empty file: {fp}")
                except Exception:
                    print(f"Skipping empty file (cannot remove): {fp}")
                continue
            if sz < max_size:
                small_file_tuples.append((fp, sz))
        except Exception:
            # skip files we can't stat
            continue

    if not small_file_tuples:
        print(f"No added proxy files under {max_size // 1024} KB found.")
        return

    # Process available small files in a loop so that deleting an empty/unused
    # file will allow the collector to continue with the next file.
    args = parse_args(default_limit=1)
    file_lock_arg = getattr(args, "file_lock", None)

    while small_file_tuples:
        try:
            selected_file = min(small_file_tuples, key=lambda t: t[1])[0]
        except Exception:
            selected_file = random.choice([t[0] for t in small_file_tuples])

        # remove this candidate from the list so we don't retry it repeatedly
        small_file_tuples = [t for t in small_file_tuples if t[0] != selected_file]
        print(f"Selected file: {selected_file}")

        lock_name = os.path.basename(selected_file) + ".lock"
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
            continue

        try:
            db = init_db("mysql")
            # Minimal diagnostics: ensure file exists before processing
            if not os.path.exists(selected_file):
                print(f"Selected file no longer exists: {selected_file}")
                db.close()
                continue

            added_count = 0
            skipped_count = 0
            proxies_extracted_total = 0
            sample_extracted = []
            processed_strings = []

            # Read entire file and extract proxies from full content
            try:
                with open(selected_file, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
            except Exception as e:
                print(f"Failed to read file {selected_file}: {e}")
                content = ""

            if not content or not content.strip():
                print("File content empty after reading")
                # No content to extract from — delete the file to avoid reprocessing
                try:
                    os.remove(selected_file)
                    print(f"Deleted empty file: {selected_file}")
                except Exception as e:
                    print(f"Failed to delete file {selected_file}: {e}")
                db.close()
                continue

            try:
                extracted_proxies = extract_proxies(content)
            except Exception as e:
                print(f"extract_proxies failed: {e}")
                extracted_proxies = []

            proxies_extracted_total = len(extracted_proxies)
            # If extraction yielded nothing, delete the source file and continue
            if proxies_extracted_total == 0:
                try:
                    os.remove(selected_file)
                    print(f"Deleted file with no extracted proxies: {selected_file}")
                except Exception as e:
                    print(f"Failed to delete file {selected_file}: {e}")
                db.close()
                continue

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
                    skipped_count += 1
                    processed_strings.append(proxy_str)
                else:
                    try:
                        db.add(proxy_str)
                        added_count += 1
                        processed_strings.append(proxy_str)
                    except Exception as e:
                        print(f"Failed to add proxy {proxy_str}: {e}")

            db.close()

            print(f"Extracted proxies: {proxies_extracted_total}")
            print(f"Skipped (already present): {skipped_count}")
            if sample_extracted:
                print(f"Sample extracted proxies: {sample_extracted}")
            print(f"Added {added_count} proxies to database")

            # Remove processed proxy lines/occurrences from the file instead of deleting it
            if processed_strings:
                try:
                    removed = remove_string_from_file(
                        selected_file,
                        processed_strings,
                        clear_trailing_empty_lines=True,
                    )
                    if removed:
                        print(f"Removed processed proxies from file: {selected_file}")
                    else:
                        print(
                            f"Failed to remove processed proxies from file: {selected_file}"
                        )
                except Exception as e:
                    print(f"Error removing processed proxies from file: {e}")
            # After successfully processing and if parameter single is set, break the loop to only process one file per run
            if args.single:
                break
        finally:
            try:
                per_lock.release()
                print(f"Lock released: {per_lock.file_path}")
            except Exception as e:
                print(f"Error releasing lock for {selected_file}: {e}")
            _global_file_lock = None


if __name__ == "__main__":
    collect()
