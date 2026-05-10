#!/usr/bin/env python3

from __future__ import annotations

import gc
import os
import platform
import re
import subprocess
import sys
import threading
import time
from pathlib import Path
from typing import Iterable, Sequence, TextIO

from dotenv import load_dotenv

from src.utils.process.resources_usage import (
    check_system_resources,
    get_system_usage,
)
from src.func_date import parse_interval_to_seconds
from src.utils.process.spawn import run_command_with_logging

CWD = Path(__file__).resolve().parent
os.chdir(CWD)

CRONTAB_STATE_DIR = CWD / "tmp/crontab"
CRONTAB_LOG_DIR = CWD / "tmp/logs/crontab"

CRONTAB_STATE_DIR.mkdir(parents=True, exist_ok=True)
CRONTAB_LOG_DIR.mkdir(parents=True, exist_ok=True)


def get_venv_name() -> str:
    if (CWD / ".venv").is_dir():
        return ".venv"
    if (CWD / "venv").is_dir():
        return "venv"
    return ".venv"


VENV_NAME = get_venv_name()


def resolve_python_bin() -> str:
    os_name = platform.system()

    if os_name == "Windows":
        candidates = [
            CWD / "bin" / "py.cmd",
            CWD / VENV_NAME / "Scripts" / "python.exe",
        ]
    else:
        candidates = [
            CWD / "bin" / "py",
            CWD / VENV_NAME / "bin" / "python",
        ]

    for candidate in candidates:
        if candidate.exists():
            return str(candidate)

    return sys.executable if os_name == "Windows" else "python3"


PYTHON_BIN = resolve_python_bin()


def build_path() -> str:
    os_name = platform.system()

    venv_bin = CWD / VENV_NAME / ("Scripts" if os_name == "Windows" else "bin")

    parts = [
        "/usr/local/bin",
        "/usr/bin",
        "/usr/local/sbin",
        "/usr/sbin",
        "/sbin",
        "/bin",
        str(CWD / "bin"),
        str(CWD / "node_modules/.bin"),
        str(CWD / "vendor/bin"),
        str(venv_bin),
    ]

    if os.environ.get("PATH"):
        parts.append(os.environ["PATH"])

    return os.pathsep.join(parts)


os.environ["PATH"] = build_path()

load_dotenv(dotenv_path=CWD / ".env", override=True, verbose=False, encoding="utf-8")


def should_run_job(
    interval: str,
    file_path: str | Path | None = None,
    max_cpu_percent: int = 50,
    max_ram_percent: int = 50,
    skip_resource_checking: bool = False,
    ensure_run_daily: bool = False,
) -> bool:
    if file_path is None:
        file_path = CRONTAB_STATE_DIR / interval
    elif isinstance(file_path, str):
        file_path = Path(file_path)

    now = int(time.time())
    interval_seconds = parse_interval_to_seconds(interval)

    try:
        last = int(file_path.read_text().strip()) if file_path.exists() else 0
    except Exception:
        last = 0

    elapsed = now - last

    if elapsed < interval_seconds:
        return False

    if skip_resource_checking or check_system_resources(
        max_cpu_percent,
        max_ram_percent,
    ):
        file_path.write_text(str(now))
        return True

    if ensure_run_daily and interval_seconds <= 86400 and elapsed >= 86400:
        file_path.write_text(str(now))
        return True

    return False


def echo_skip_or_run(label: str, condition: bool) -> None:
    if condition:
        print(f"Running {label} job.")
    else:
        print(f"Skipping {label} job.")

    cpu, ram = get_system_usage(sample_cpu_seconds=0.2)
    print(f"CPU={cpu}%, RAM={ram}%")


def cleanup_old_files(
    directories: Iterable[Path],
    days: int = 40,
    dry_run: bool = False,
) -> None:
    cutoff = time.time() - (days * 86400)

    for directory in directories:
        if not directory.exists():
            continue

        for path in directory.rglob("*"):
            if not path.is_file():
                continue

            try:
                if path.stat().st_mtime < cutoff:
                    if dry_run:
                        print(f"[DRY] {path}")
                    else:
                        path.unlink()
                        print(f"Deleted {path}")
            except Exception:
                continue


gc.collect()

if should_run_job("5-m"):
    echo_skip_or_run("5 minutes", True)
    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/blacklist_remover.py")],
        log_file=CRONTAB_LOG_DIR / "cleanup-blacklist.log",
    )

gc.collect()

if should_run_job("30-m"):
    echo_skip_or_run("30 minutes", True)

    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/geoIp.py"), "--limit=100"],
        log_file=CRONTAB_LOG_DIR / "geoip.log",
    )

    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/proxy_tun2socks_stability.py"), "--limit=100"],
        log_file=CRONTAB_LOG_DIR / "tun2socks.log",
    )

gc.collect()

if should_run_job(
    "1-h",
    file_path=CRONTAB_STATE_DIR / "proxy-collectors",
    skip_resource_checking=True,
):
    echo_skip_or_run("Proxy Collectors", True)
    run_command_with_logging(
        [PYTHON_BIN, "artisan/proxyCollector.py"],
        log_file=CRONTAB_LOG_DIR / "proxyCollector.log",
    )

gc.collect()

if should_run_job("45-m"):
    echo_skip_or_run("Filter Open Port", True)

    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/filter_open_port.py")],
        log_file=CRONTAB_LOG_DIR / "filter_open_port.log",
    )

gc.collect()

if should_run_job("1-h"):
    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/filter_duplicate_ips.py")],
        log_file=CRONTAB_LOG_DIR / "filter-duplicate-ips.log",
    )
    echo_skip_or_run("1 hour", True)

gc.collect()

if should_run_job("1-h", ensure_run_daily=True):
    echo_skip_or_run("1 hour", True)

    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/proxy_https_checker.py")],
        log_file=CRONTAB_LOG_DIR / "proxy_https_checker.log",
    )

    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/proxy_socks5_checker.py")],
        log_file=CRONTAB_LOG_DIR / "proxy-socks5.log",
    )

gc.collect()

if should_run_job("24-h"):
    echo_skip_or_run("24 hours", True)
    run_command_with_logging(
        ["bash", "-e", str(CWD / "bin/backup-db")],
        log_file=CRONTAB_LOG_DIR / "backup-db.log",
    )

    run_command_with_logging(
        ["php", str(CWD / "artisan/cleaner.php")],
        log_file=CRONTAB_LOG_DIR / "php-cleaner.log",
    )
    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "artisan/cleaner.py")],
        log_file=CRONTAB_LOG_DIR / "python-cleaner.log",
    )

gc.collect()

if should_run_job(
    "5-m", file_path=CRONTAB_STATE_DIR / "resource-usage", skip_resource_checking=True
):
    echo_skip_or_run("Resource usage", True)

    run_command_with_logging(
        [PYTHON_BIN, str(CWD / "src/utils/process/process_usage.py")],
        log_file=CRONTAB_LOG_DIR / "resource-usage.log",
    )

    with open(CWD / "tmp/logs/system-usage.json", "w") as f:
        subprocess.run(
            [
                PYTHON_BIN,
                str(CWD / "src/utils/process/process_usage.py"),
                "--json",
            ],
            stdout=f,
            stderr=subprocess.STDOUT,
            text=True,
        )

gc.collect()

if should_run_job(
    "12-h",
    file_path=CRONTAB_STATE_DIR / "sqlite-wal-checkpoint",
    ensure_run_daily=True,
):
    echo_skip_or_run("SQLite WAL + Vacuum maintenance", True)

    # =========================
    # tmp database
    # =========================
    run_command_with_logging(
        [
            "sqlite3",
            str(CWD / "tmp/database.sqlite"),
            "PRAGMA wal_checkpoint(TRUNCATE);",
        ],
        log_file=CRONTAB_LOG_DIR / "sqlite-wal-tmp.log",
    )

    run_command_with_logging(
        [
            "sqlite3",
            str(CWD / "tmp/database.sqlite"),
            "PRAGMA wal_checkpoint(SQLITE_CHECKPOINT_TRUNCATE);",
        ],
        log_file=CRONTAB_LOG_DIR / "sqlite-wal-truncate-tmp.log",
    )

    run_command_with_logging(
        [
            "sqlite3",
            str(CWD / "tmp/database.sqlite"),
            "VACUUM;",
        ],
        log_file=CRONTAB_LOG_DIR / "sqlite-vacuum-tmp.log",
    )

    # =========================
    # src database
    # =========================
    run_command_with_logging(
        [
            "sqlite3",
            str(CWD / "src/database.sqlite"),
            "PRAGMA wal_checkpoint(TRUNCATE);",
        ],
        log_file=CRONTAB_LOG_DIR / "sqlite-wal-src.log",
    )

    run_command_with_logging(
        [
            "sqlite3",
            str(CWD / "src/database.sqlite"),
            "PRAGMA wal_checkpoint(SQLITE_CHECKPOINT_TRUNCATE);",
        ],
        log_file=CRONTAB_LOG_DIR / "sqlite-wal-truncate-src.log",
    )

    run_command_with_logging(
        [
            "sqlite3",
            str(CWD / "src/database.sqlite"),
            "VACUUM;",
        ],
        log_file=CRONTAB_LOG_DIR / "sqlite-vacuum-src.log",
    )
