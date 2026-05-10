from __future__ import annotations

import json
import os
import re
import sys
import time
from datetime import datetime

import psutil

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../..")))

from src.func_console import *
from src.utils.parse_args import parse_args
from src.utils.process.resources_usage import display_system_usage

IS_WINDOWS = os.name == "nt"

MAX_CMD_LENGTH = 300

# Exact executable/runtime mapping
RUNTIME_DEFINITIONS: dict[str, str] = {
    # Python
    "python": "Python",
    "python3": "Python",
    "pythonw": "Python",
    # PHP
    "php": "PHP",
    "php-cgi": "PHP",
    "php-fpm": "PHP",
    # Node.js
    "node": "Node.js",
    "npm": "Node.js",
    "pnpm": "Node.js",
    "yarn": "Node.js",
    # Bun / Deno
    "bun": "Bun",
    "deno": "Deno",
    # Java
    "java": "Java",
    "javac": "Java",
    "gradle": "Java",
    "mvn": "Java",
    # Go
    "go": "Go",
    # Rust
    "cargo": "Rust",
    "rustc": "Rust",
    # Ruby
    "ruby": "Ruby",
    "bundle": "Ruby",
    # Perl
    "perl": "Perl",
    # Databases
    "mysqld": "MySQL",
    "mysql": "MySQL",
    "mariadbd": "MariaDB",
    "postgres": "PostgreSQL",
    "redis-server": "Redis",
    "mongod": "MongoDB",
    # Containers
    "docker": "Docker",
    "dockerd": "Docker",
    "containerd": "Docker",
    # Web servers
    "nginx": "Nginx",
    "apache2": "Apache",
    "httpd": "Apache",
    # Misc
    "git": "Git",
    "ffmpeg": "FFmpeg",
}

# Optional aliases for scripts/tools
RUNTIME_ALIASES: dict[str, str] = {
    "tsserver.js": "Node.js",
    "eslintserver.js": "Node.js",
    "jsonservermain": "Node.js",
    "lsp_server.py": "Python",
    "typingsinstaller.js": "Node.js",
    "artisan": "PHP",
}


def bytes_to_mb(b: int) -> float:
    return b / (1024 * 1024)


def normalize_cmd(cmd: str) -> str:
    # Fix Windows NT path prefixes
    if cmd.startswith("\\??\\") or cmd.startswith("\\\\?\\"):
        return cmd[4:]

    return cmd.strip()


def normalize_exe_name(name: str) -> str:
    name = name.lower().strip()

    if IS_WINDOWS:
        for suffix in (".exe", ".bat", ".cmd", ".com"):
            if name.endswith(suffix):
                return name[: -len(suffix)]

    return name


def detect_runtime(name: str, cmd: str) -> str | None:
    normalized_name = normalize_exe_name(name)

    # Exact executable lookup
    runtime = RUNTIME_DEFINITIONS.get(normalized_name)

    if runtime:
        return runtime

    cmd_lower = cmd.lower()

    # Alias lookup
    for alias, runtime in RUNTIME_ALIASES.items():
        if alias in cmd_lower:
            return runtime

    # Tokenized commandline lookup
    tokens = set(re.findall(r"[a-zA-Z0-9._-]+", cmd_lower))

    for keyword, runtime in RUNTIME_DEFINITIONS.items():
        if keyword in tokens:
            return runtime

    return None


def runtime_color(runtime: str) -> str:
    runtime_lower = runtime.lower()

    color_map = {
        "python": cyan,
        "php": magenta,
        "node.js": green,
        "java": yellow,
        "go": cyan,
        "rust": red,
        "ruby": red,
        "perl": blue,
        "mysql": yellow,
        "mariadb": yellow,
        "postgresql": blue,
        "redis": red,
        "mongodb": green,
        "docker": blue,
        "nginx": green,
        "apache": yellow,
        "git": magenta,
        "ffmpeg": cyan,
        "bun": yellow,
        "deno": green,
    }

    color_func = color_map.get(runtime_lower, white)

    return color_func(runtime)


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

    use_json = getattr(args, "json", False)

    limit = max(1, int(getattr(args, "limit", 20)))

    interval = max(0.1, float(getattr(args, "interval", 0.5)))

    current_pid = os.getpid()

    parent_pid = os.getppid()

    script_name = os.path.basename(__file__).lower()

    processes: list[psutil.Process] = []

    # Warm-up CPU measurement
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

        except (
            psutil.NoSuchProcess,
            psutil.AccessDenied,
            psutil.ZombieProcess,
        ):
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

            runtime = detect_runtime(raw_name, cmd)

            if runtime is None:
                continue

            if len(cmd) > MAX_CMD_LENGTH:
                cmd = cmd[:MAX_CMD_LENGTH] + "..."

            cpu_percent = round(float(proc.cpu_percent(None)), 2)

            mem_info = proc.memory_info()

            mem_mb = round(bytes_to_mb(mem_info.rss), 2)

            mem_percent = round(float(proc.memory_percent()), 2)

            results.append(
                {
                    "runtime": runtime,
                    "pid": proc.pid,
                    "name": raw_name,
                    "cmd": cmd,
                    "cpu_percent": cpu_percent,
                    "mem_mb": mem_mb,
                    "mem_percent": mem_percent,
                }
            )

        except (
            psutil.NoSuchProcess,
            psutil.AccessDenied,
            psutil.ZombieProcess,
        ):
            continue

        except Exception:
            continue

    # Sort by CPU then RAM
    results.sort(
        key=lambda x: (
            x["cpu_percent"],
            x["mem_percent"],
        ),
        reverse=True,
    )

    results = results[:limit]

    if use_json:
        payload = {
            "timestamp": datetime.now().strftime("%Y-%m-%dT%H:%M:%S"),
            "count": len(results),
            "processes": results,
            "system": display_system_usage(0.1, None, json=True),
        }

        print(json.dumps(payload, indent=2))

        return

    print(f"\n=== {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} ===")

    for item in results:
        runtime_s = runtime_color(item["runtime"])

        cpu_s = color_percent_value_text(
            item["cpu_percent"],
            f"{item['cpu_percent']:.2f}%",
            True,
        )

        ram_s = color_percent_value_text(
            item["mem_percent"],
            f"{item['mem_percent']:.2f}%",
            True,
        )

        print(
            f"[{runtime_s}] "
            f"{item['cmd']} | "
            f"CPU {cpu_s} | "
            f"RAM {item['mem_mb']:.2f}MB ({ram_s})"
        )

    display_system_usage(0.1)


if __name__ == "__main__":
    main()
