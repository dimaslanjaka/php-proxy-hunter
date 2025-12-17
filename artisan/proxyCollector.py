#!/usr/bin/env python3
"""
Proxy Collector and Indexer
"""

import os
import sys
import signal
import argparse
import random
from pathlib import Path
from typing import Optional

# Add parent directory to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.ProxyDB import ProxyDB
from src.shared import init_db
from src.func import get_relative_path
from proxy_hunter import extract_proxies, Proxy
from src.utils.file.FileLockHelper import FileLockHelper

# Global constants
LOCK_FILE_PATH = get_relative_path("tmp/locks/proxyCollector.lock")
ASSETS_PROXIES_DIR = get_relative_path("assets/proxies")

# Global lock reference for signal handler
_global_file_lock = None


def cleanup_and_exit(signum=None, frame=None):
    """Clean up and release lock on exit"""
    global _global_file_lock
    if _global_file_lock:
        try:
            _global_file_lock.release()
            print(f"\nLock released: {_global_file_lock.file_path}")
        except Exception as e:
            print(f"\nError releasing lock: {e}")
    sys.exit(0)


def get_added_proxy_files():
    """Get all added-*.txt files from assets/proxies folder (recursive)."""
    p = Path(ASSETS_PROXIES_DIR)
    if not p.exists():
        return []

    # Use rglob to search recursively for files named added-*.txt
    return [str(fp) for fp in sorted(p.rglob("added-*.txt")) if fp.is_file()]


def process_file(file_path, proxy_db, batch_size=10):
    """
    Process a single proxy file in batches.

    Args:
        file_path (str): Path to the proxy file
        proxy_db: ProxyDB instance
        batch_size (int): Number of lines to process per batch
    """
    try:
        if not os.path.exists(file_path):
            print(f"File not found: {file_path}")
            return

        file_size = os.path.getsize(file_path)
        print(
            f"Processing {os.path.basename(file_path)}: {file_size / 1024:.2f} KB (batch size: {batch_size})"
        )

        line_count = 0
        lines_to_keep = []
        current_batch = []
        batch_num = 0
        lines_processed_in_batch = 0

        with open(file_path, "r", encoding="utf-8") as f:
            for line_num, line in enumerate(f, 1):
                line = line.strip()

                if not line:
                    continue

                line_count += 1
                current_batch.append((line, line_num))
                lines_processed_in_batch += 1

                # Process batch when it reaches batch_size
                if len(current_batch) >= batch_size:
                    batch_num += 1
                    print(f"  Processing batch {batch_num}...")

                    for batch_line, batch_line_num in current_batch:
                        success = process_line(batch_line, proxy_db, batch_line_num)
                        if not success:
                            lines_to_keep.append(batch_line)

                    current_batch = []

                    # Stop after processing this batch - don't continue to next file
                    print(
                        f"  Processed {lines_processed_in_batch} lines. Stopping for this run."
                    )
                    break

        # If we processed any lines, keep unprocessed ones and rewrite file
        if lines_processed_in_batch > 0:
            # Add remaining lines from file that weren't read
            try:
                with open(file_path, "r", encoding="utf-8") as f:
                    all_lines = [l.strip() for l in f.readlines() if l.strip()]

                # Keep lines we haven't processed yet
                processed_count = 0
                for line in all_lines:
                    if processed_count >= lines_processed_in_batch:
                        lines_to_keep.append(line)
                    processed_count += 1
            except:
                pass

        print(f"  Total lines processed: {lines_processed_in_batch}")

        # Rewrite file with only the lines that weren't processed
        with open(file_path, "w", encoding="utf-8") as f:
            for line in lines_to_keep:
                f.write(line + "\n")

        if lines_to_keep:
            print(f"  Kept {len(lines_to_keep)} unprocessed lines in file")
        else:
            print(f"  File cleared - all proxies processed")

    except Exception as e:
        print(f"Error processing {file_path}: {e}")


def process_line(line: str, proxy_db: ProxyDB, line_num: int = 0):
    """
    Process a single line from proxy file.

    Returns True if successfully processed, False otherwise.

    Args:
        line (str): Line content
        proxy_db (ProxyDB): ProxyDB instance
        line_num (int): Line number in the file
    """
    try:
        proxies = extract_proxies(line)
        print(f"[{line_num}]: Total proxies extracted: {len(proxies)}")

        for proxy in proxies:
            data = {"proxy": proxy.proxy}

            # Only add username if it's a valid string and not empty or "-"
            if (
                isinstance(proxy.username, str)
                and proxy.username.strip()
                and proxy.username != "-"
            ):
                data["username"] = proxy.username

            # Only add password if it's a valid string and not empty or "-"
            if (
                isinstance(proxy.password, str)
                and proxy.password.strip()
                and proxy.password != "-"
            ):
                data["password"] = proxy.password

            proxy_db.update_data(proxy.proxy, data)

        return True  # Line processed successfully
    except Exception as e:
        print(f"Error processing line {line_num}: {e}")
        return False  # Failed to process, keep the line


def main():
    """Main entry point"""
    # Parse CLI arguments
    parser = argparse.ArgumentParser(description="Proxy Collector and Indexer")
    parser.add_argument(
        "--single",
        action="store_true",
        help="Pick a single random file instead of processing all files",
    )
    parser.add_argument(
        "--shuffle",
        action="store_true",
        help="Shuffle discovered added-*.txt files before processing",
    )
    parser.add_argument(
        "--batch-size",
        type=int,
        default=10,
        help="Number of lines to process per file run (default: 10)",
    )
    args = parser.parse_args()

    # Use a single global declaration for the per-file lock variable
    global _global_file_lock

    # Set up signal handlers for graceful cleanup
    signal.signal(signal.SIGINT, cleanup_and_exit)  # CTRL+C
    signal.signal(signal.SIGTERM, cleanup_and_exit)  # Termination signal

    # Process files using per-file locks (avoid a single global lock)
    try:
        proxy_db = init_db(db_type="mysql")

        # Ensure locks directory exists and use it for per-file locks
        locks_dir = get_relative_path("tmp/locks")
        try:
            os.makedirs(locks_dir, exist_ok=True)
        except Exception:
            pass

        # Get added proxy files
        added_files = get_added_proxy_files()
        print(f"Found {len(added_files)} added proxy files")

        # Optionally shuffle file processing order to reduce contention
        if getattr(args, "shuffle", False):
            random.shuffle(added_files)
            print("Shuffled processing order for added proxy files")

        if not added_files:
            print("No proxy files to process")
            return

        # Process files based on CLI args
        if args.single:
            # Pick a single random file
            file_path = random.choice(added_files)
            print(f"Attempting to process random file: {file_path}")

            lock_name = os.path.basename(file_path) + ".lock"
            per_lock_path = os.path.join(locks_dir, lock_name)
            per_lock = FileLockHelper(per_lock_path)
            _global_file_lock = per_lock

            if not per_lock.lock():
                print(f"Skipping {file_path}: locked by another process")
                _global_file_lock = None
            else:
                try:
                    print(f"Processing locked file: {file_path}")
                    process_file(file_path, proxy_db, args.batch_size)
                finally:
                    try:
                        per_lock.release()
                        print(f"Lock released: {per_lock.file_path}")
                    except Exception as e:
                        print(f"Error releasing lock for {file_path}: {e}")
                    _global_file_lock = None
        else:
            # Process all files, skipping those locked by other processes
            print(f"Processing all {len(added_files)} files:")
            for file_path in added_files:
                print(f"  - {file_path}")

                lock_name = os.path.basename(file_path) + ".lock"
                per_lock_path = os.path.join(locks_dir, lock_name)
                per_lock = FileLockHelper(per_lock_path)
                _global_file_lock = per_lock

                if not per_lock.lock():
                    print(f"    Skipping (locked by another process)")
                    _global_file_lock = None
                    continue

                try:
                    print(f"    Processing locked file")
                    process_file(file_path, proxy_db, args.batch_size)
                finally:
                    try:
                        per_lock.release()
                        print(f"    Lock released: {per_lock.file_path}")
                    except Exception as e:
                        print(f"    Error releasing lock for {file_path}: {e}")
                    _global_file_lock = None

    finally:
        # Nothing global to release here; per-file locks are released after each file.
        pass


if __name__ == "__main__":
    main()
