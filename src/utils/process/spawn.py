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


def _finalize_log(
    log_file: Path,
    process: subprocess.Popen[bytes],
    command: Sequence[str],
) -> None:
    exit_code = process.wait()

    with log_file.open("a", encoding="utf-8") as fh:
        fh.write(
            f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Exit code: {exit_code}\n\n"
            "====================\n\n"
            f"Command: {' '.join(command)}\n"
        )


def run_command_with_logging(
    command: Iterable[str],
    log_file: str | Path | None = None,
    background: bool = True,
    cwd: Path | None = None,
    append: bool = False,
) -> None:
    cmd = [str(c) for c in command]
    env = os.environ.copy()

    if cwd:
        os.chdir(cwd)

    def write_header(fh: TextIO) -> None:
        fh.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Running: {' '.join(cmd)}\n")

    # =====================================================
    # Foreground mode (live output)
    # =====================================================
    if not background:
        process = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            cwd=cwd,
            env=env,
            text=True,
            bufsize=1,
        )

        assert process.stdout is not None

        fh = None
        if log_file:
            lf = Path(log_file)
            lf.parent.mkdir(parents=True, exist_ok=True)
            mode = "a" if append else "w"
            fh = lf.open(mode, encoding="utf-8")
            write_header(fh)

        for line in process.stdout:
            print(line, end="")
            if fh:
                fh.write(line)

        process.wait()

        if fh:
            fh.close()

        return

    # =====================================================
    # Background mode (log required)
    # =====================================================
    if log_file is None:
        raise ValueError("log_file is required when background=True")

    lf = Path(log_file)
    lf.parent.mkdir(parents=True, exist_ok=True)
    mode = "a" if append else "w"

    with lf.open(mode, encoding="utf-8") as fh:
        write_header(fh)

        process = subprocess.Popen(
            cmd,
            stdout=fh,
            stderr=subprocess.STDOUT,
            cwd=cwd,
            env=env,
            text=True,
        )

    threading.Thread(
        target=_finalize_log,
        args=(lf, process, cmd),
        daemon=True,
    ).start()


if __name__ == "__main__":
    CWD = Path(__file__).resolve().parent.parent.parent.parent
    print(f"Current working directory: {CWD}")

    # Example command to run in background with logging
    outFile = CWD / "tmp/logs/example.log"
    run_command_with_logging(
        ["echo", "Hello, World!"],
        log_file=outFile,
        background=False,
    )
    print(f"Log written to: {outFile}")

    # Example command to run in foreground with live output
    run_command_with_logging(
        ["ping", "google.com"],
        background=False,
    )
