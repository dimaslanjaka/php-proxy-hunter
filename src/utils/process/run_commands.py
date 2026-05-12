from __future__ import annotations

import os
import platform
import subprocess
from hashlib import md5
import sys
from pathlib import Path
from typing import Iterable

# Register project root to sys.path
PROJECT_ROOT = Path(__file__).resolve().parent.parent.parent.parent
if str(PROJECT_ROOT) not in sys.path:
    sys.path.append(str(PROJECT_ROOT))

from src.utils.process.spawn import build_env_path


def run_commands(
    commands: Iterable[Iterable[str]],
    cwd: Path | None = None,
    background: bool = True,
) -> None:
    cmds = [list(cmd) for cmd in commands]
    is_windows = platform.system() == "Windows"
    env = os.environ.copy()
    env.setdefault("PATH", build_env_path())

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
        proc = subprocess.Popen(["cmd", "/c", str(script_path)], cwd=cwd, env=env)
    else:
        proc = subprocess.Popen(["bash", str(script_path)], cwd=cwd, env=env)

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
    # Clean up old log file if exists
    os.remove(outfile) if outfile.exists() else None
    wait_cmd = (
        ["C:\\Windows\\System32\\timeout.exe", "/t", "1", "/nobreak", ">", "nul"]
        if platform.system() == "Windows"
        else ["sleep", "1"]
    )
    run_commands(
        [
            ["echo", "First command", ">", str(outfile)],
            wait_cmd,
            ["echo", "Second command", ">>", str(outfile)],
            wait_cmd,
            ["echo", "Third command", ">>", str(outfile)],
        ],
        cwd=CWD,
        background=True,
    )
    print(f"Commands are running in background. Check log: {outfile}")
    # Example of running multiple commands live in foreground
    run_commands(
        [["ping", "google.com"], ["echo", "Hello, World!"]], cwd=CWD, background=False
    )
