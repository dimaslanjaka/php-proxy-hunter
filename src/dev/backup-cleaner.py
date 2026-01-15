#!/usr/bin/env python3
"""
backups/cleanups.py

Delete .sql files older than a given number of days in the backups folder.

Usage examples:
  python backups/cleanups.py            # deletes .sql older than 2 days in this folder
  python backups/cleanups.py --dry-run  # show what would be deleted
  python backups/cleanups.py --days 7   # change age threshold

Options:
  --path PATH   Path to folder containing backups (default: script directory)
  --days N      Files older than N days will be removed (default: 2)
  --dry-run     Print files that would be removed without deleting
"""
from __future__ import annotations

import argparse
import os
import sys
import time
from pathlib import Path
from typing import Tuple

PROJECT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "../../"))
sys.path.append(PROJECT_DIR)


def find_old_sql_files(path: Path, days: int) -> list[Path]:
    now = time.time()
    cutoff = now - days * 86400
    files: list[Path] = []
    for p in path.glob("*.sql"):
        try:
            if p.stat().st_mtime < cutoff:
                files.append(p)
        except OSError:
            # ignore files we can't stat
            continue
    return files


def delete_files(files: list[Path], dry_run: bool) -> Tuple[int, int]:
    deleted = 0
    failed = 0
    for p in files:
        if dry_run:
            print("DRY-RUN: would remove", p)
            continue
        try:
            p.unlink()
            print("Removed", p)
            deleted += 1
        except OSError as e:
            print("Failed to remove", p, "->", e)
            failed += 1
    return deleted, failed


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Delete .sql files older than N days in backups folder"
    )
    p.add_argument(
        "--path",
        "-p",
        default=None,
        help="Path to backups folder (default: script dir)",
    )
    p.add_argument("--days", "-d", type=int, default=2, help="Age in days (default: 2)")
    p.add_argument(
        "--dry-run",
        action="store_true",
        help="Show files to be deleted without removing them",
    )
    return p.parse_args()


def main() -> int:
    args = parse_args()
    if args.path:
        folder = Path(args.path)
    else:
        # default to the project backups folder
        folder = Path(PROJECT_DIR, "backups").resolve()

    if not folder.exists() or not folder.is_dir():
        print(
            "Error: path does not exist or is not a directory:", folder, file=sys.stderr
        )
        return 2

    files = find_old_sql_files(folder, int(args.days))
    if not files:
        print(
            "No .sql files older than {} day(s) found in {}".format(args.days, folder)
        )
        return 0

    print(
        "Found {} .sql file(s) older than {} day(s) in {}".format(
            len(files), args.days, folder
        )
    )

    deleted, failed = delete_files(files, bool(args.dry_run))
    if args.dry_run:
        print("Dry-run complete. {} candidate(s) listed.".format(len(files)))
        return 0

    print("Deleted: {}  Failed: {}".format(deleted, failed))
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
