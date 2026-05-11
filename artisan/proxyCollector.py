import os
import random
import signal
import sys
from pathlib import Path
from typing import List, Tuple

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from proxy_hunter import extract_proxies
from src.func import get_relative_path
from src.shared import init_db
from src.utils.file.FileLockHelper import FileLockHelper
from src.utils.file.remove_string_from_file import remove_string_from_file
from src.utils.parse_args import parse_args

# Global lock reference for signal handler
_global_file_lock = None
LOCKS_DIR = get_relative_path("tmp/locks")
ASSETS_PROXIES_DIR = get_relative_path("assets/proxies")


def get_added_proxy_files():
    """Get all added-*.txt files from assets/proxies folder (recursive).

    Also include project-root files `dead.txt` and `proxies.txt` when present.
    Returns a deduplicated, sorted list of file paths as strings.
    """
    files = []

    # Search assets/proxies for added-*.txt recursively (if folder exists)
    p = Path(ASSETS_PROXIES_DIR)
    if p.exists():
        files.extend([fp for fp in sorted(p.rglob("added-*.txt")) if fp.is_file()])

    # Also include root-level files if they exist
    for name in ("dead.txt", "proxies.txt"):
        root_fp = Path(get_relative_path(name))
        if root_fp.exists() and root_fp.is_file():
            files.append(root_fp)

    # Deduplicate while preserving determinism, then return sorted string paths
    unique_paths = []
    seen = set()
    for fp in files:
        s = str(fp)
        if s not in seen:
            seen.add(s)
            unique_paths.append(s)

    unique_paths.sort()
    return unique_paths


def cleanup_and_exit(signum=None, frame=None):
    global _global_file_lock
    if _global_file_lock:
        try:
            _global_file_lock.release()
            print(f"\nLock released: {_global_file_lock.file_path}")
        except Exception as e:
            print(f"\nError releasing lock: {e}")
    sys.exit(0)


def collect(limit_kb: int | float = 800):
    # Register signal handlers so locks are released on interrupt/terminate
    signal.signal(signal.SIGINT, cleanup_and_exit)
    signal.signal(signal.SIGTERM, cleanup_and_exit)

    files = get_added_proxy_files()
    if not files:
        print("No added proxy files found.")
        return

    # Convert KB → bytes
    max_size = int(limit_kb * 1024)

    # Filter files by size (only files smaller than limit_kb KB)
    small_file_tuples: List[Tuple[str, int]] = []
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
            continue

    if not small_file_tuples:
        print(f"No added proxy files under {limit_kb} KB found.")
        return

    args = parse_args(default_limit=1)
    file_lock_arg = getattr(args, "file_lock", None)

    while small_file_tuples:
        try:
            selected_file = min(small_file_tuples, key=lambda t: t[1])[0]
        except Exception:
            selected_file = random.choice([t[0] for t in small_file_tuples])

        # remove selected file from pool
        small_file_tuples = [t for t in small_file_tuples if t[0] != selected_file]

        print(f"Selected file: {selected_file}")

        lock_name = os.path.basename(selected_file) + ".lock"
        per_lock_path = (
            file_lock_arg if file_lock_arg else os.path.join(LOCKS_DIR, lock_name)
        )

        per_lock = FileLockHelper(per_lock_path)

        global _global_file_lock
        _global_file_lock = per_lock

        if not per_lock.lock():
            print(f"Skipping {selected_file}: locked by another process")
            _global_file_lock = None
            continue

        try:
            db = init_db()

            if not os.path.exists(selected_file):
                print(f"Selected file no longer exists: {selected_file}")
                db.close()
                continue

            added_count = 0
            skipped_count = 0
            proxies_extracted_total = 0
            sample_extracted = []
            processed_strings = []

            try:
                with open(selected_file, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
            except Exception as e:
                print(f"Failed to read file {selected_file}: {e}")
                content = ""

            if not content or not content.strip():
                print("File content empty after reading")
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

            if added_count == 0:
                print("No new proxies added from this file; continuing to next file.")
                continue

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
    args = parse_args(
        additional=[
            {
                "flag": "--size-limit",
                "type": float,
                "default": 800,
                "help": "Max file size in KB to process (default: 800)",
                "dest": "size_limit",
            }
        ]
    )
    collect(args.attr("size_limit", 800))
