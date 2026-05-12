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
from typing import Iterable, List, Sequence, TextIO, Union


def build_env_path():
    env = os.environ.copy()
    project_dir = Path(__file__).parent.parent.parent
    venv_dir = (
        project_dir / "venv"
        if (project_dir / "venv").is_dir()
        else project_dir / ".venv"
    )
    additional_paths: List[Union[Path, str]] = [
        project_dir / "bin",
        venv_dir / "bin",
        venv_dir / "Scripts",
        project_dir / "vendor" / "bin",
        project_dir / "node_modules" / ".bin",
    ]
    is_windows = platform.system() == "Windows"
    if is_windows:
        local_appdata = os.getenv("LOCALAPPDATA")
        if local_appdata:
            additional_paths.append(Path(local_appdata) / "nvm")
            additional_paths.append(
                r"C:\nvm4w\nodejs;C:\Program Files\Nox\bin;D:\Program Files\Nox\bin;C:\Program Files\Git\cmd;C:\Program Files\Git\usr\bin;C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;C:\laragon\bin\php\php-8.4.11-Win32-vs17-x64;C:\laragon\bin\git\bin;C:\laragon\bin\python\python-3.13;C:\laragon\bin\memcached\memcached-1.6.8-win64-mingw;D:\Program Files\Microsoft VS Code;C:\Users\Dell\AppData\Local\Programs\Ollama"
            )
    separator = ";" if is_windows else ":"
    for p in additional_paths:
        if isinstance(p, str):
            p = Path(p)
        if p.is_dir():
            env["PATH"] = f"{p}{separator}{env['PATH']}"
    return env.get("PATH", os.defpath)


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
    env.setdefault("PATH", build_env_path())

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
