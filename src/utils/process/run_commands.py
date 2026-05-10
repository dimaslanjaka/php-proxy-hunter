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

    for cmd in cmds:
        # =====================================================
        # BACKGROUND MODE (silent)
        # =====================================================
        if background:
            subprocess.Popen(
                cmd,
                cwd=cwd,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                stdin=subprocess.DEVNULL,
                creationflags=subprocess.CREATE_NEW_PROCESS_GROUP if is_windows else 0,
            )
            continue

        # =====================================================
        # FOREGROUND MODE (live output)
        # =====================================================
        proc = subprocess.Popen(
            cmd,
            cwd=cwd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
        )

        assert proc.stdout is not None

        for line in proc.stdout:
            print(line, end="")

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
