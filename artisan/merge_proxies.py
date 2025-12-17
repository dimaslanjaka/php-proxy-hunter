#!/usr/bin/env python3
"""
Merge small added-*.txt proxy files into a single merged file.

Creates `assets/proxies/added-merged-small.txt` and moves merged source
files into `assets/proxies/merged-backup/` to avoid reprocessing.
"""
import os
from pathlib import Path
import shutil
from datetime import datetime

REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
PROXIES_DIR = os.path.join(REPO_ROOT, "assets", "proxies")
MERGED_NAME_BASE = "added-merged"
BACKUP_DIR = os.path.join(PROXIES_DIR, "merged-backup")
SIZE_THRESHOLD = 10 * 1024  # files <= 10 KB are considered "small"


def find_candidates():
    p = Path(PROXIES_DIR)
    if not p.exists():
        return []
    files = []
    for fp in sorted(p.iterdir()):
        if not fp.is_file():
            continue
        name = fp.name
        if not name.startswith("added-"):
            continue
        # skip already merged files or backup
        if "merged" in name or name.startswith(MERGED_NAME_BASE):
            continue
        try:
            size = fp.stat().st_size
        except Exception:
            continue
        if size <= SIZE_THRESHOLD:
            files.append(fp)
    return files


def merge_files(candidates):
    # build timestamped merged filename
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    merged_filename = f"{MERGED_NAME_BASE}-{timestamp}.txt"
    merged_path = Path(PROXIES_DIR) / merged_filename
    lines_set = set()

    # load existing merged lines from any previous merged files
    p = Path(PROXIES_DIR)
    for existing in sorted(p.glob(f"{MERGED_NAME_BASE}*.txt")):
        try:
            with open(existing, "r", encoding="utf-8") as f:
                for l in f:
                    l = l.strip()
                    if l:
                        lines_set.add(l)
        except Exception:
            continue

    merged_from = []
    for fp in candidates:
        with open(fp, "r", encoding="utf-8", errors="ignore") as f:
            for l in f:
                l = l.strip()
                if l:
                    lines_set.add(l)
        merged_from.append(fp.name)

    if not lines_set:
        print("No lines to write to merged file.")
        return merged_path, merged_from

    # write merged unique lines to timestamped file
    with open(merged_path, "w", encoding="utf-8") as f:
        for l in sorted(lines_set):
            f.write(l + "\n")

    return merged_path, merged_from


def archive_sources(candidates):
    if not candidates:
        return []
    os.makedirs(BACKUP_DIR, exist_ok=True)
    moved = []
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    for fp in candidates:
        # append timestamp to archived filename to avoid collisions
        dest_name = f"{fp.stem}-{timestamp}{fp.suffix}"
        dest = Path(BACKUP_DIR) / dest_name
        try:
            shutil.move(str(fp), str(dest))
            moved.append(fp.name)
        except Exception as e:
            print(f"Failed to move {fp.name}: {e}")
    return moved


def main():
    print(
        f"Scanning {PROXIES_DIR} for small added-*.txt files (<= {SIZE_THRESHOLD} bytes)"
    )
    candidates = find_candidates()
    print(f"Found {len(candidates)} candidate(s) to merge")
    for c in candidates:
        print(f"  - {c.name} ({c.stat().st_size} bytes)")

    merged_path, merged_from = merge_files(candidates)
    if merged_from:
        print(f"Wrote merged file: {merged_path}")
        moved = archive_sources(candidates)
        print(f"Archived {len(moved)} source files to {BACKUP_DIR}")
    else:
        print("Nothing merged.")


if __name__ == "__main__":
    main()
