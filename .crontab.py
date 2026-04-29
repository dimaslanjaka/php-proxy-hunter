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
from typing import Iterable, Sequence

from dotenv import load_dotenv

from src.utils.process.resources_usage import check_system_resources, get_system_usage

CWD = Path(__file__).resolve().parent
# Ensure the script runs with the working directory set to the project root
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
    # Default to .venv when neither exists yet.
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
        if candidate.exists() and candidate.is_file():
            return str(candidate)
    if os_name == "Windows":
        return sys.executable
    return "python3"


PYTHON_BIN = resolve_python_bin()


def build_path() -> str:
    os_name = platform.system()
    if os_name in {"Darwin", "Linux"}:
        venv_bin = CWD / VENV_NAME / "bin"
    else:
        venv_bin = CWD / VENV_NAME / "Scripts"

    essential = [
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
    existing = os.environ.get("PATH", "")
    if existing:
        essential.append(existing)
    return os.pathsep.join(essential)


os.environ["PATH"] = build_path()


load_dotenv(dotenv_path=CWD / ".env")


def parse_interval_to_seconds(interval: str) -> int:
    """Convert a compact interval string to seconds.

    Accepts both hyphenated and compact forms, e.g. `5-m` or `5m`,
    `1-h` or `1h`, `2-d` or `2d`, `1-w` or `1w`, `1-y` or `1y`.
    """

    # Allow optional hyphen between number and unit (e.g. '5-m' or '5m')
    match = re.fullmatch(r"\s*(\d+)\s*-?\s*([mhdwy])\s*", interval.lower())
    if not match:
        raise ValueError(
            "Invalid interval format. Use '<number><unit>' or '<number>-<unit>' where "
            "unit is one of: m (minute), h (hour), d (day), w (week), y (year)."
        )

    amount = int(match.group(1))
    unit = match.group(2)

    unit_seconds = {
        "m": 60,
        "h": 60 * 60,
        "d": 24 * 60 * 60,
        "w": 7 * 24 * 60 * 60,
        "y": 365 * 24 * 60 * 60,
    }
    return amount * unit_seconds[unit]


def should_run_job(
    interval: str,
    file_path: str | Path | None = None,
    max_cpu_percent: int = 50,
    max_ram_percent: int = 50,
    skip_resource_checking: bool = False,
    ensure_run_daily: bool = True,
) -> bool:
    """Return whether a scheduled job should run and update its state timestamp.

    The timestamp file stores the last successful run time as a UNIX epoch.
    A job can run only when the configured interval has elapsed. By default,
    CPU and RAM usage must also be below the provided thresholds.

    Args:
        interval: Minimum interval between runs in '<number>-<unit>' format.
            Supported units are m (minute), h (hour), w (week), y (year).
        file_path: Optional state file used to persist the last successful run
            timestamp. If omitted, defaults to CRONTAB_STATE_DIR / interval.
        max_cpu_percent: Maximum allowed CPU usage percentage.
        max_ram_percent: Maximum allowed RAM usage percentage.
        skip_resource_checking: If True, bypass CPU/RAM checks.
        ensure_run_daily: If True, ensure short-interval jobs (<= 24h)
            will run at least once every 24 hours even if resource checks fail.

    Returns:
        True if the job should run now (and state is updated), otherwise False.
    """
    # Accept `file_path` as `None`, `str`, or `Path`. If omitted, use a
    # per-interval state file under `tmp/crontab/<interval>`.
    if file_path is None:
        file_path = CRONTAB_STATE_DIR / interval
    elif isinstance(file_path, str):
        file_path = Path(file_path)

    current_time = int(time.time())
    interval_seconds = parse_interval_to_seconds(interval)

    try:
        if file_path.is_file():
            last_fetch = int(file_path.read_text(encoding="utf-8").strip())
        else:
            last_fetch = 0
    except ValueError:
        last_fetch = 0

    elapsed = current_time - last_fetch

    # If the configured interval has elapsed, prefer to run only when
    # resource checks pass. However, to ensure short-interval jobs (<= 24h)
    # run at least once per day, allow a forced run if it's been >= 24h
    # since the last run even when resource checks fail.
    if elapsed >= interval_seconds:
        if skip_resource_checking or check_system_resources(
            max_cpu_percent, max_ram_percent
        ):
            file_path.write_text(str(current_time), encoding="utf-8")
            return True

        # Fallback: for intervals at or below 1 day, force a run if the job
        # is stale for 24 hours or more and `ensure_run_daily` is enabled.
        if (
            ensure_run_daily
            and interval_seconds <= 24 * 60 * 60
            and elapsed >= 24 * 60 * 60
        ):
            file_path.write_text(str(current_time), encoding="utf-8")
            return True

        return False

    return False


def _finalize_log(
    log_file: Path, process: subprocess.Popen[bytes], command: Sequence[str]
) -> None:
    exit_code = process.wait()
    with log_file.open("a", encoding="utf-8") as fh:
        fh.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Exit code: {exit_code}\n\n")
        fh.write("====================\n\n")
        fh.write(f"Command: {' '.join(command)}\n")


def log_command(log_file: Path, command: Iterable[str]) -> None:
    cmd = [str(part) for part in command]
    log_file.parent.mkdir(parents=True, exist_ok=True)

    with log_file.open("w", encoding="utf-8") as fh:
        fh.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Running: {' '.join(cmd)}\n")

    with log_file.open("a", encoding="utf-8") as fh:
        process = subprocess.Popen(
            cmd,
            stdout=fh,
            stderr=subprocess.STDOUT,
            cwd=CWD,
            env=os.environ.copy(),
        )

    thread = threading.Thread(
        target=_finalize_log, args=(log_file, process, cmd), daemon=True
    )
    thread.start()


def echo_skip_or_run(label: str, condition: bool) -> None:
    if condition:
        print(f"Running {label} job.")
    else:
        print(f"Skipping {label} job.")

    cpu_usage, ram_usage = get_system_usage(sample_cpu_seconds=0.2)
    cpu_text = f"{cpu_usage}%" if cpu_usage is not None else "N/A"
    ram_text = f"{ram_usage}%" if ram_usage is not None else "N/A"
    print(f"Resource usage: CPU={cpu_text}, RAM={ram_text}")


def cleanup_old_files(
    directories: Iterable[Path],
    days: int = 40,
    dry_run: bool = False,
) -> None:
    """Delete files older than `days` based on last modification time."""
    now = time.time()
    cutoff = now - (days * 24 * 60 * 60)

    for directory in directories:
        if not directory.exists():
            continue

        for path in directory.rglob("*"):
            if not path.is_file():
                continue

            try:
                mtime = path.stat().st_mtime
            except OSError:
                continue

            if mtime < cutoff:
                if dry_run:
                    print(f"[DRY RUN] Would delete: {path}")
                else:
                    try:
                        path.unlink()
                        print(f"Deleted old file: {path}")
                    except OSError as e:
                        print(f"Failed to delete {path}: {e}")


run_5m_skip_resources = should_run_job(
    "5-m",
    file_path=CRONTAB_STATE_DIR / "no-resource-check-5-m",
    skip_resource_checking=False,
)
if run_5m_skip_resources:
    log_command(
        CRONTAB_LOG_DIR / "resource-usage.log",
        [PYTHON_BIN, str(CWD / "src/utils/process/process_usage.py")],
    )

gc.collect()

# run every 5 minutes
run_5m = should_run_job("5-m")
if run_5m:
    echo_skip_or_run("5 minutes", True)
    log_command(
        CRONTAB_LOG_DIR / "cleanup-blacklist.log",
        [PYTHON_BIN, str(CWD / "artisan/blacklist_remover.py")],
    )
else:
    echo_skip_or_run("5 minutes", False)

gc.collect()

# run every 30 minutes (regular jobs — resource-checked)
run_30m = should_run_job("30-m")
if run_30m:
    echo_skip_or_run("30 minutes", True)
    log_command(
        CRONTAB_LOG_DIR / "geoip.log", [PYTHON_BIN, str(CWD / "artisan/geoIp.py")]
    )
    log_command(
        CRONTAB_LOG_DIR / "tun2socks-stability-check.log",
        [PYTHON_BIN, str(CWD / "artisan/proxy_tun2socks_stability.py"), "--limit=100"],
    )
else:
    echo_skip_or_run("30 minutes", False)

gc.collect()

should_run_proxy_collectors = should_run_job(
    "4-h",
    file_path=CRONTAB_STATE_DIR / "proxy-collectors",
    skip_resource_checking=True,
)
if should_run_proxy_collectors:
    echo_skip_or_run("4 hours", True)
    log_command(
        CRONTAB_LOG_DIR / "proxyCollector2.log",
        [PYTHON_BIN, "artisan/proxyCollector2.py", "--batch-size=500", "--shuffle"],
    )
    log_command(
        CRONTAB_LOG_DIR / "proxyCollector.log",
        [PYTHON_BIN, "artisan/proxyCollector.py", "--batch-size=500", "--shuffle"],
    )
else:
    echo_skip_or_run("4 hours", False)


gc.collect()

run_45m = should_run_job("45-m")
if run_45m:
    echo_skip_or_run("45 minutes (filter_open_port)", True)
    log_command(
        CRONTAB_LOG_DIR / "filter_open_port.log",
        [PYTHON_BIN, str(CWD / "artisan/filter_open_port.py"), "--limit=100"],
    )
else:
    echo_skip_or_run("45 minutes (filter_open_port)", False)


gc.collect()

run_1h = should_run_job("1-h")
if run_1h:
    log_command(
        CRONTAB_LOG_DIR / "filter-duplicate-ips.log",
        [
            PYTHON_BIN,
            str(CWD / "artisan/filter_duplicate_ips.py"),
            "--limit=100",
            "--include-untested",
        ],
    )
    log_command(
        CRONTAB_LOG_DIR / "proxy-socks5-checker.log",
        [PYTHON_BIN, str(CWD / "artisan/proxy_socks5_checker.py"), "--limit=100"],
    )
    echo_skip_or_run("1 hour", True)
else:
    echo_skip_or_run("1 hour", False)

gc.collect()

run_3h = should_run_job("3-h", skip_resource_checking=True)
if run_3h:
    echo_skip_or_run("3 hours", True)
    log_command(
        CRONTAB_LOG_DIR / "proxy_checker_httpx.log",
        [PYTHON_BIN, str(CWD / "artisan/proxy_checker_httpx.py")],
    )
else:
    echo_skip_or_run("3 hours", False)


gc.collect()

run_4h = should_run_job("4-h")
if run_4h:
    echo_skip_or_run("4 hours", True)
else:
    echo_skip_or_run("4 hours", False)


gc.collect()

run_6h = should_run_job("6-h")
if run_6h:
    echo_skip_or_run("6 hours", True)
else:
    echo_skip_or_run("6 hours", False)


gc.collect()

run_12h = should_run_job("12-h")
if run_12h:
    echo_skip_or_run("12 hours", True)
    tmp_db = CWD / "tmp/database.sqlite"
    src_db = CWD / "src/database.sqlite"

    if (CWD / "tmp/database.sqlite-wal").is_file():
        print("Checkpointing and truncating WAL file...")
        subprocess.run(
            ["sqlite3", str(tmp_db), "PRAGMA wal_checkpoint(TRUNCATE);"],
            cwd=CWD,
            check=False,
            env=os.environ.copy(),
        )
        print(f"{tmp_db} WAL file truncated.")

    if (CWD / "src/database.sqlite-wal").is_file():
        print("Checkpointing and truncating WAL file...")
        subprocess.run(
            ["sqlite3", str(src_db), "PRAGMA wal_checkpoint(TRUNCATE);"],
            cwd=CWD,
            check=False,
            env=os.environ.copy(),
        )
        print(f"{src_db} WAL file truncated.")
else:
    echo_skip_or_run("12 hours", False)


gc.collect()

run_24h = should_run_job("24-h")
if run_24h:
    echo_skip_or_run("24 hours", True)
    log_command(
        CRONTAB_LOG_DIR / "backup-db.log", ["bash", "-e", str(CWD / "bin/backup-db")]
    )
    log_command(
        CRONTAB_LOG_DIR / "php-cleaner.log", ["php", str(CWD / "artisan/cleaner.php")]
    )
    log_command(
        CRONTAB_LOG_DIR / "python-cleaner.log",
        [PYTHON_BIN, str(CWD / "artisan/cleaner.py")],
    )
    log_command(
        CRONTAB_LOG_DIR / "cleanup-backups.log",
        [
            "find",
            str(CWD / "backups"),
            "-type",
            "f",
            "-name",
            "*.sql",
            "-mtime",
            "+7",
            "-exec",
            "rm",
            "-f",
            "{}",
            ";",
        ],
    )
    print("Old backups removed, keeping only the last 7 days.")
    log_command(
        CRONTAB_LOG_DIR / "cleanup-logs.log",
        [
            "find",
            str(CWD / "tmp/logs"),
            "-type",
            "f",
            "-name",
            "*.log",
            "-mtime",
            "+30",
            "-exec",
            "rm",
            "-f",
            "{}",
            ";",
        ],
    )
    print("Old log files removed, keeping only the last 30 days.")
    # Run proxy-classifier-lookup once per day (moved from 1h schedule)
    log_command(
        CRONTAB_LOG_DIR / "proxy-classifier-lookup.log",
        [PYTHON_BIN, str(CWD / "artisan/proxy-classifier-lookup.py"), "--limit=100"],
    )
    # Run proxyFetcher once per day (moved from 4h schedule)
    log_command(
        CRONTAB_LOG_DIR / "proxy-fetcher.log",
        [PYTHON_BIN, str(CWD / "artisan/proxyFetcher.py")],
    )
else:
    echo_skip_or_run("24 hours", False)


gc.collect()

should_run_3d = should_run_job("72-h")
if should_run_3d:
    echo_skip_or_run("72 hours", True)
    log_command(
        CRONTAB_LOG_DIR / "cleanup-backups-3d.log",
        [PYTHON_BIN, str(CWD / "src/dev/backup-cleaner.py")],
    )
else:
    echo_skip_or_run("72 hours", False)

gc.collect()

# run every week
should_run_weekly = should_run_job("1-w", max_cpu_percent=90, max_ram_percent=90)
if should_run_weekly:
    echo_skip_or_run("1 week", True)
else:
    echo_skip_or_run("1 week", False)

gc.collect()

should_run_10d = should_run_job("10-d", max_cpu_percent=90, max_ram_percent=90)
if should_run_10d:
    echo_skip_or_run("10 days", True)
    cleanup_old_files(
        [CRONTAB_STATE_DIR, CRONTAB_LOG_DIR],
        days=40,
    )
else:
    echo_skip_or_run("10 days", False)
