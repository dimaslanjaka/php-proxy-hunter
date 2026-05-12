from __future__ import annotations

import os
import platform
import subprocess
from hashlib import md5
import sys
import shutil
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
    new_terminal: bool = False,
    new_terminal_auto_close: bool = False,
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
    # Execute script (optionally in a new terminal window)
    # -----------------------------
    if is_windows:
        if new_terminal:
            # Use 'start' to open a new cmd window and run the script.
            # Use /k to keep the window open, or /c to auto-close when finished.
            keep_flag = "/k" if not new_terminal_auto_close else "/c"
            proc = subprocess.Popen(
                ["cmd", "/c", "start", "cmd", keep_flag, str(script_path)],
                cwd=cwd,
                env=env,
            )
        else:
            proc = subprocess.Popen(["cmd", "/c", str(script_path)], cwd=cwd, env=env)
    else:
        if new_terminal:
            # Try common terminal emulators; fall back to running in current shell
            emulators = [
                "x-terminal-emulator",
                "gnome-terminal",
                "konsole",
                "xfce4-terminal",
                "mate-terminal",
                "terminator",
                "alacritty",
                "kitty",
                "xterm",
            ]
            found = next((e for e in emulators if shutil.which(e)), None)
            if found:
                # Most emulators accept '-e' followed by a command.
                # When auto-close is requested, run the script directly so the terminal closes on finish.
                if new_terminal_auto_close:
                    proc = subprocess.Popen(
                        [found, "-e", "bash", str(script_path)], cwd=cwd, env=env
                    )
                else:
                    # Try to keep the new terminal open after the script finishes.
                    if found == "xterm":
                        proc = subprocess.Popen(
                            [found, "-hold", "-e", "bash", str(script_path)],
                            cwd=cwd,
                            env=env,
                        )
                    elif found == "gnome-terminal":
                        proc = subprocess.Popen(
                            [
                                found,
                                "--",
                                "bash",
                                "-c",
                                f"bash {script_path}; exec bash",
                            ],
                            cwd=cwd,
                            env=env,
                        )
                    else:
                        # Generic fallback: ask the shell to exec bash after running the script
                        proc = subprocess.Popen(
                            [
                                found,
                                "-e",
                                "bash",
                                "-c",
                                f"bash {script_path}; exec bash",
                            ],
                            cwd=cwd,
                            env=env,
                        )
            else:
                proc = subprocess.Popen(["bash", str(script_path)], cwd=cwd, env=env)
        else:
            proc = subprocess.Popen(["bash", str(script_path)], cwd=cwd, env=env)


if __name__ == "__main__":
    # Example usage
    run_commands(
        [
            ["echo", "Hello, World!"],
            ["python", "--version"],
        ],
        cwd=Path.cwd(),
    )
    # new terminal example
    run_commands(
        [
            ["echo", "This is a new terminal window!"],
            ["python", "--version"],
        ],
        cwd=Path.cwd(),
        new_terminal=True,
    )
    # new terminal with auto-close example
    run_commands(
        [
            ["echo", "This terminal will auto-close after running!"],
            ["python", "--version"],
        ],
        cwd=Path.cwd(),
        new_terminal=True,
        new_terminal_auto_close=True,
    )
