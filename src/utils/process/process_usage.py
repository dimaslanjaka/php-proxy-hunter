from __future__ import annotations

import json
import os
import sys
import time
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Callable

import psutil

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../..")))

from src.func_console import *
from src.utils.parse_args import parse_args
from src.utils.process.resources_usage import display_system_usage

IS_WINDOWS = os.name == "nt"

MAX_CMD_LENGTH = 300

PSUTIL_EXCEPTIONS = (
    psutil.NoSuchProcess,
    psutil.AccessDenied,
    psutil.ZombieProcess,
)


# =========================================================
# Runtime definition
# =========================================================


@dataclass(slots=True, frozen=True)
class RuntimeDefinition:
    runtime_name: str
    color: Callable[[str], str]


RUNTIME_DEFINITIONS: dict[str, RuntimeDefinition] = {
    # Python
    "python": RuntimeDefinition("Python", cyan),
    "python3": RuntimeDefinition("Python", cyan),
    "pythonw": RuntimeDefinition("Python", cyan),
    # PHP
    "php": RuntimeDefinition("PHP", magenta),
    "php-cgi": RuntimeDefinition("PHP", magenta),
    "php-fpm": RuntimeDefinition("PHP", magenta),
    # Node.js
    "node": RuntimeDefinition("Node.js", green),
    "npm": RuntimeDefinition("Node.js", green),
    "pnpm": RuntimeDefinition("Node.js", green),
    "yarn": RuntimeDefinition("Node.js", green),
    # Bun / Deno
    "bun": RuntimeDefinition("Bun", yellow),
    "deno": RuntimeDefinition("Deno", green),
    # Java
    "java": RuntimeDefinition("Java", yellow),
    "javac": RuntimeDefinition("Java", yellow),
    "gradle": RuntimeDefinition("Java", yellow),
    "mvn": RuntimeDefinition("Java", yellow),
    # Go
    "go": RuntimeDefinition("Go", cyan),
    # Rust
    "cargo": RuntimeDefinition("Rust", red),
    "rustc": RuntimeDefinition("Rust", red),
    # Ruby
    "ruby": RuntimeDefinition("Ruby", red),
    "bundle": RuntimeDefinition("Ruby", red),
    # Perl
    "perl": RuntimeDefinition("Perl", blue),
    # Databases
    "mysqld": RuntimeDefinition("MySQL", yellow),
    "mysql": RuntimeDefinition("MySQL", yellow),
    "mariadbd": RuntimeDefinition("MariaDB", yellow),
    "postgres": RuntimeDefinition("PostgreSQL", blue),
    "redis-server": RuntimeDefinition("Redis", red),
    "mongod": RuntimeDefinition("MongoDB", green),
    # Containers
    "docker": RuntimeDefinition("Docker", blue),
    "dockerd": RuntimeDefinition("Docker", blue),
    "containerd": RuntimeDefinition("Docker", blue),
    # Web servers
    "nginx": RuntimeDefinition("Nginx", green),
    "apache2": RuntimeDefinition("Apache", yellow),
    "httpd": RuntimeDefinition("Apache", yellow),
    # Misc
    "git": RuntimeDefinition("Git", magenta),
    "ffmpeg": RuntimeDefinition("FFmpeg", cyan),
}


# =========================================================
# Runtime aliases
# =========================================================

RUNTIME_ALIASES: dict[str, str] = {
    "tsserver.js": "node",
    "eslintserver.js": "node",
    "jsonservermain": "node",
    "typingsinstaller.js": "node",
    "lsp_server.py": "python",
    "artisan": "php",
}


# =========================================================
# Helpers
# =========================================================


def bytes_to_mb(value: int) -> float:
    return round(value / 1048576, 2)


def normalize_cmd(cmd: str) -> str:
    if cmd.startswith(("\\??\\", "\\\\?\\")):
        cmd = cmd[4:]

    return cmd.strip()


def normalize_exe_name(name: str) -> str:
    name = name.lower().strip()

    if IS_WINDOWS:
        name = Path(name).stem.lower()

    return name


def detect_runtime(name: str, cmd_lower: str) -> RuntimeDefinition | None:
    normalized_name = normalize_exe_name(name)

    runtime = RUNTIME_DEFINITIONS.get(normalized_name)

    if runtime:
        return runtime

    for alias, runtime_key in RUNTIME_ALIASES.items():
        if alias in cmd_lower:
            return RUNTIME_DEFINITIONS[runtime_key]

    for keyword, runtime in RUNTIME_DEFINITIONS.items():
        if keyword in cmd_lower:
            return runtime

    return None


def color_runtime(runtime: RuntimeDefinition) -> str:
    return runtime.color(runtime.runtime_name)


# =========================================================
# Main
# =========================================================


def main() -> None:
    args = parse_args(
        additional=[
            {
                "flags": "--json",
                "action_type": "store_true",
                "help": "Output JSON",
            },
            {
                "flags": "--limit",
                "type": int,
                "default": 20,
                "help": "Maximum processes to display",
            },
            {
                "flags": "--interval",
                "type": float,
                "default": 0.5,
                "help": "CPU sample interval",
            },
        ]
    )

    use_json = bool(getattr(args, "json", False))

    limit = max(1, int(getattr(args, "limit", 20)))

    interval = max(0.1, float(getattr(args, "interval", 0.5)))

    current_pid = os.getpid()

    parent_pid = os.getppid()

    script_name = Path(__file__).name.lower()

    processes: list[psutil.Process] = []

    # Warm-up CPU stats
    for proc in psutil.process_iter(
        [
            "pid",
            "name",
            "cmdline",
        ]
    ):
        try:
            proc.cpu_percent(None)

            processes.append(proc)

        except PSUTIL_EXCEPTIONS:
            continue

    time.sleep(interval)

    results: list[dict] = []

    for proc in processes:
        try:
            if proc.pid in (current_pid, parent_pid):
                continue

            info = proc.info

            raw_name = info.get("name") or ""

            cmdline = info.get("cmdline") or []

            cmd = normalize_cmd(" ".join(cmdline) if cmdline else raw_name)

            cmd_lower = cmd.lower()

            if script_name in cmd_lower:
                continue

            runtime = detect_runtime(raw_name, cmd_lower)

            if runtime is None:
                continue

            if len(cmd) > MAX_CMD_LENGTH:
                cmd = f"{cmd[:MAX_CMD_LENGTH]}..."

            mem_info = proc.memory_info()

            cpu_percent = round(proc.cpu_percent(None), 2)

            mem_percent = round(proc.memory_percent(), 2)

            results.append(
                {
                    "runtime": runtime.runtime_name,
                    "runtime_colored": color_runtime(runtime),
                    "pid": proc.pid,
                    "cmd": cmd,
                    "cpu_percent": cpu_percent,
                    "mem_mb": bytes_to_mb(mem_info.rss),
                    "mem_percent": mem_percent,
                }
            )

        except PSUTIL_EXCEPTIONS:
            continue

    results.sort(
        key=lambda item: (
            item["cpu_percent"],
            item["mem_percent"],
        ),
        reverse=True,
    )

    results = results[:limit]

    if use_json:
        print(
            json.dumps(
                {
                    "timestamp": datetime.now().isoformat(timespec="seconds"),
                    "count": len(results),
                    "processes": results,
                    "system": display_system_usage(0.1, None, json=True),
                },
                indent=2,
            )
        )

        return

    print(f"\n=== {datetime.now():%Y-%m-%d %H:%M:%S} ===")

    for item in results:
        cpu_percent = item["cpu_percent"]

        mem_percent = item["mem_percent"]

        cpu_s = color_percent_value_text(
            cpu_percent,
            f"{cpu_percent:.2f}%",
            True,
        )

        ram_s = color_percent_value_text(
            mem_percent,
            f"{mem_percent:.2f}%",
            True,
        )

        print(
            f"[{item['runtime_colored']}] "
            f"{item['cmd']} | "
            f"CPU {cpu_s} | "
            f"RAM {item['mem_mb']:.2f}MB ({ram_s})"
        )

    display_system_usage(0.1)


if __name__ == "__main__":
    main()
