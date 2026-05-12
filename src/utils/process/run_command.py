from __future__ import annotations

import os
import shlex
import subprocess
import sys
from pathlib import Path
from typing import Iterable

# Register project root to sys.path
PROJECT_ROOT = Path(__file__).resolve().parent.parent.parent.parent
if str(PROJECT_ROOT) not in sys.path:
    sys.path.append(str(PROJECT_ROOT))

from src.func import get_relative_path
from src.func_date import get_current_rfc3339_time
from src.utils.file import get_python_path
from src.utils.process.spawn import build_env_path


def run_command(
    command: str | Iterable[str],
    cwd: Path | None = None,
    env: dict | None = None,
    output_file: Path | str | None = None,
):
    """Run an external command.

    Args:
        command: Either a single command string (shell-like) or an iterable of
            argument strings (e.g. "python --version" or ["python", "--version"]).
            If a string is provided it will be split using `shlex.split`.
        cwd: Working directory to run the command in. If ``None``, uses current cwd.
        env: Environment variables mapping to use for the subprocess. If ``None``, a copy of
            ``os.environ`` is used.
        output_file: If provided, stdout and stderr are redirected into this file and the
            command is started without streaming output back to the caller. In that case the
            child process may continue running after this function returns (background
            execution). If ``None``, the command's stdout/stderr are streamed to stdout and
            the function blocks until the subprocess exits.
    """
    env = env or os.environ.copy()
    env.setdefault("PATH", build_env_path())
    if isinstance(command, str):
        cmd = shlex.split(command)
    else:
        cmd = [str(c) for c in command]

    if output_file:
        with open(output_file, "w", encoding="utf-8") as fh:
            process = subprocess.Popen(
                cmd,
                stdout=fh,
                stderr=subprocess.STDOUT,
                cwd=cwd,
                env=env,
                text=True,
            )
    else:
        process = subprocess.Popen(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            cwd=cwd,
            env=env,
            text=True,
        )

        assert process.stdout is not None

        for line in process.stdout:
            print(line, end="")


if __name__ == "__main__":
    CWD = Path(get_relative_path(".")).resolve()
    print(f"Current working directory: {CWD}")
    os.chdir(CWD)
    python = get_python_path()
    print(f"Using Python executable: {python}")

    assert python is not None, "Python executable not found"

    run_command([python, "--version"], cwd=CWD)

    output_file = get_relative_path("tmp/logs/test_command.log")
    print(
        f"[{get_current_rfc3339_time()}] run in background with logging: {output_file}"
    )
    run_command(
        command=[python, str(CWD / "artisan/cleaner.py")],
        cwd=CWD,
        output_file=output_file,
    )
