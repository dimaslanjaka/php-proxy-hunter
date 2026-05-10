from __future__ import annotations

import gc
from hashlib import md5
import os
import platform
import re
import subprocess
import sys
import threading
import time
from pathlib import Path
from typing import Iterable, Sequence, TextIO


def run_commands(
    commands: Iterable[Iterable[str]],
    cwd: Path | None = None,
    background: bool = True,
) -> None:
    cmds = [list(cmd) for cmd in commands]
    is_windows = platform.system() == "Windows"

    # -----------------------------
    # Build script file
    # -----------------------------
    script_hash = md5(str(cmds).encode()).hexdigest()
    ext = ".bat" if is_windows else ".sh"

    script_path = Path.cwd() / "tmp" / "runners" / f"{script_hash}{ext}"
    script_path.parent.mkdir(parents=True, exist_ok=True)

    lines: list[str] = []

    if is_windows:
        lines.append("@echo off")
    else:
        lines.append("#!/usr/bin/env bash")
        lines.append("set -e")

    for cmd in cmds:
        lines.append(" ".join(cmd))

    script_path.write_text("\n".join(lines), encoding="utf-8")

    if not is_windows:
        script_path.chmod(0o755)

    # -----------------------------
    # Execute script
    # -----------------------------
    if is_windows:
        proc = subprocess.Popen(
            ["cmd", "/c", str(script_path)],
            cwd=cwd,
        )
    else:
        proc = subprocess.Popen(
            ["bash", str(script_path)],
            cwd=cwd,
        )

    # -----------------------------
    # Background vs foreground
    # -----------------------------
    if background:
        return  # fire & forget

    proc.wait()


if __name__ == "__main__":
    CWD = Path(__file__).resolve().parent.parent.parent.parent
    print(f"Current working directory: {CWD}")

    # Example of running multiple commands in background with logging
    outfile = CWD / "tmp/logs/multi_command.log"
    run_commands(
        [
            ["echo", "First command"],
            ["echo", "Second command"],
            ["echo", "Third command"],
        ],
        cwd=CWD,
        background=True,
    )
    print(f"Commands are running in background. Check log: {outfile}")
    # Example of running multiple commands live in foreground
    run_commands(
        [["ping", "google.com"], ["echo", "Hello, World!"]], cwd=CWD, background=False
    )
