from __future__ import annotations

import gc
import os
import re
import subprocess
import sys
import threading
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable, Iterable, TypedDict

from dotenv import load_dotenv

from src.utils.process.resources_usage import (
    check_system_resources,
    get_system_usage,
)

# =========================
# PATHS / ENVIRONMENT
# =========================

CWD = Path(__file__).resolve().parent
os.chdir(CWD)

IS_WINDOWS = os.name == "nt"

CRONTAB_STATE_DIR = CWD / "tmp/crontab"
CRONTAB_LOG_DIR = CWD / "tmp/logs/crontab"

CRONTAB_STATE_DIR.mkdir(parents=True, exist_ok=True)
CRONTAB_LOG_DIR.mkdir(parents=True, exist_ok=True)


def timestamp() -> str:
    return time.strftime("%Y-%m-%d %H:%M:%S")


def get_venv_name() -> str:
    for name in (".venv", "venv"):
        if (CWD / name).is_dir():
            return name

    return ".venv"


VENV_NAME = get_venv_name()

VENV_BIN = CWD / VENV_NAME / ("Scripts" if IS_WINDOWS else "bin")


def resolve_python_bin() -> str:
    candidates = (
        [
            CWD / "bin/py.cmd",
            VENV_BIN / "python.exe",
        ]
        if IS_WINDOWS
        else [
            CWD / "bin/py",
            VENV_BIN / "python",
        ]
    )

    for candidate in candidates:
        if candidate.is_file():
            return str(candidate)

    return sys.executable if IS_WINDOWS else "python3"


PYTHON_BIN = resolve_python_bin()


def build_path() -> str:
    paths = [
        "/usr/local/bin",
        "/usr/bin",
        "/usr/local/sbin",
        "/usr/sbin",
        "/sbin",
        "/bin",
        str(CWD / "bin"),
        str(CWD / "node_modules/.bin"),
        str(CWD / "vendor/bin"),
        str(VENV_BIN),
        os.environ.get("PATH", ""),
    ]

    return os.pathsep.join(filter(None, paths))


os.environ["PATH"] = build_path()

ENV = os.environ.copy()

load_dotenv(dotenv_path=CWD / ".env")

# =========================
# TYPES
# =========================


class Job(TypedDict):
    log_file: str | Path
    command: Iterable[str]


@dataclass(slots=True)
class CronJob:
    name: str
    interval: str
    commands: list[Job] = field(default_factory=list)
    callback: Callable[[], None] | None = None
    file_path: Path | None = None
    ensure_run_daily: bool = False
    skip_resource_checking: bool = False
    max_cpu_percent: int = 50
    max_ram_percent: int = 50


# =========================
# HELPERS
# =========================


def parse_interval_to_seconds(interval: str) -> int:
    match = re.fullmatch(
        r"\s*(\d+)\s*-?\s*([mhdwy])\s*",
        interval.lower(),
    )

    if not match:
        raise ValueError(f"Invalid interval: {interval}")

    amount = int(match.group(1))
    unit = match.group(2)

    unit_seconds = {
        "m": 60,
        "h": 3600,
        "d": 86400,
        "w": 604800,
        "y": 31536000,
    }

    return amount * unit_seconds[unit]


def should_run_job(
    interval: str,
    file_path: str | Path | None = None,
    max_cpu_percent: int = 50,
    max_ram_percent: int = 50,
    skip_resource_checking: bool = False,
    ensure_run_daily: bool = False,
) -> bool:
    state_file = (
        Path(file_path) if file_path is not None else CRONTAB_STATE_DIR / interval
    )

    current_time = int(time.time())
    interval_seconds = parse_interval_to_seconds(interval)

    try:
        last_run = (
            int(state_file.read_text(encoding="utf-8").strip())
            if state_file.is_file()
            else 0
        )
    except ValueError:
        last_run = 0

    elapsed = current_time - last_run

    if elapsed < interval_seconds:
        return False

    resources_ok = skip_resource_checking or check_system_resources(
        max_cpu_percent=max_cpu_percent,
        max_ram_percent=max_ram_percent,
    )

    if resources_ok:
        state_file.write_text(str(current_time), encoding="utf-8")
        return True

    if ensure_run_daily and interval_seconds <= 86400 and elapsed >= 86400:
        state_file.write_text(str(current_time), encoding="utf-8")
        return True

    return False


def echo_skip_or_run(label: str, should_run: bool) -> None:
    print(f"{'Running' if should_run else 'Skipping'} {label} job.")

    cpu_usage, ram_usage = get_system_usage(
        sample_cpu_seconds=0.2,
    )

    cpu_text = f"{cpu_usage}%" if cpu_usage is not None else "N/A"
    ram_text = f"{ram_usage}%" if ram_usage is not None else "N/A"

    print(f"Resource usage: CPU={cpu_text}, RAM={ram_text}")


# =========================
# PROCESS EXECUTION
# =========================


def run_process(
    log_file: str | Path,
    command: Iterable[str],
) -> int:
    cmd = [str(part) for part in command]

    log_path = Path(log_file)

    log_path.parent.mkdir(
        parents=True,
        exist_ok=True,
    )

    start_time = time.time()

    with log_path.open("w", encoding="utf-8") as fh:
        fh.write(f"[{timestamp()}] " f"Running: {' '.join(cmd)}\n\n")

        process = subprocess.Popen(
            cmd,
            stdout=fh,
            stderr=subprocess.STDOUT,
            cwd=CWD,
            env=ENV,
            text=True,
        )

        exit_code = process.wait()

        duration = round(
            time.time() - start_time,
            2,
        )

        fh.write(
            "\n"
            f"[{timestamp()}] Finished\n"
            f"Exit code : {exit_code}\n"
            f"Duration  : {duration} sec\n"
        )

    return exit_code


def log_command(
    log_file: str | Path,
    command: Iterable[str],
) -> threading.Thread:
    def worker() -> None:
        try:
            run_process(
                log_file=log_file,
                command=command,
            )
        finally:
            gc.collect()

    thread = threading.Thread(
        target=worker,
        daemon=True,
        name="log-command",
    )

    thread.start()

    return thread


def log_command_chain(
    jobs: list[Job],
) -> threading.Thread:
    def worker() -> None:
        try:
            for job in jobs:
                try:
                    run_process(
                        log_file=job["log_file"],
                        command=job["command"],
                    )

                except Exception as exc:
                    log_path = Path(job["log_file"])

                    with log_path.open(
                        "a",
                        encoding="utf-8",
                    ) as fh:
                        fh.write(
                            "\n"
                            f"[{timestamp()}] "
                            f"ERROR: "
                            f"{type(exc).__name__}: {exc}\n"
                        )

        finally:
            gc.collect()

    thread = threading.Thread(
        target=worker,
        daemon=True,
        name="log-command-chain",
    )

    thread.start()

    return thread


# =========================
# MAINTENANCE
# =========================


def truncate_sqlite_wal() -> None:
    databases = [
        CWD / "tmp/database.sqlite",
        CWD / "src/database.sqlite",
    ]

    for db in databases:
        wal_file = Path(f"{db}-wal")

        if not wal_file.exists():
            continue

        print(f"Checkpointing WAL: {db}")

        subprocess.run(
            [
                "sqlite3",
                str(db),
                "PRAGMA wal_checkpoint(TRUNCATE);",
            ],
            cwd=CWD,
            env=ENV,
            check=False,
        )


def cleanup_old_files(
    directories: Iterable[Path],
    days: int = 40,
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
                    path.unlink()
                    print(f"Deleted: {path}")

            except OSError as exc:
                print(f"Failed deleting {path}: {exc}")


# =========================
# JOB DEFINITIONS
# =========================

CRON_JOBS: list[CronJob] = [
    CronJob(
        name="Blacklist Cleanup",
        interval="5-m",
        ensure_run_daily=True,
        commands=[
            {
                "log_file": (CRONTAB_LOG_DIR / "cleanup-blacklist.log"),
                "command": [
                    PYTHON_BIN,
                    str(CWD / "artisan/blacklist_remover.py"),
                ],
            }
        ],
    ),
    CronJob(
        name="30 minutes",
        interval="30-m",
        ensure_run_daily=True,
        commands=[
            {
                "log_file": (CRONTAB_LOG_DIR / "geoip.log"),
                "command": [
                    PYTHON_BIN,
                    str(CWD / "artisan/geoIp.py"),
                    "--limit=100",
                ],
            },
            {
                "log_file": (CRONTAB_LOG_DIR / "tun2socks-stability-check.log"),
                "command": [
                    PYTHON_BIN,
                    str(CWD / "artisan/proxy_tun2socks_stability.py"),
                    "--limit=100",
                ],
            },
        ],
    ),
    CronJob(
        name="Proxy Collectors",
        interval="1-h",
        file_path=(CRONTAB_STATE_DIR / "proxy-collectors"),
        skip_resource_checking=True,
        commands=[
            {
                "log_file": (CRONTAB_LOG_DIR / "proxyCollector.log"),
                "command": [
                    PYTHON_BIN,
                    str(CWD / "artisan/proxyCollector.py"),
                    "--limit=10",
                ],
            }
        ],
    ),
    CronJob(
        name="1 hour",
        interval="1-h",
        commands=[
            {
                "log_file": (CRONTAB_LOG_DIR / "filter-duplicate-ips.log"),
                "command": [
                    PYTHON_BIN,
                    str(CWD / "artisan/filter_duplicate_ips.py"),
                    "--limit=100",
                    "--include-untested",
                ],
            },
            {
                "log_file": (CRONTAB_LOG_DIR / "filter_open_port.log"),
                "command": [
                    PYTHON_BIN,
                    str(CWD / "artisan/filter_open_port.py"),
                    "--limit=100",
                ],
            },
        ],
    ),
    CronJob(
        name="12 hours",
        interval="12-h",
        callback=truncate_sqlite_wal,
    ),
    CronJob(
        name="10 days",
        interval="10-d",
        max_cpu_percent=90,
        max_ram_percent=90,
        callback=lambda: cleanup_old_files(
            directories=[
                CRONTAB_STATE_DIR,
                CRONTAB_LOG_DIR,
            ],
            days=40,
        ),
    ),
    CronJob(
        name="Daily Backup",
        interval="1-d",
        ensure_run_daily=True,
        commands=[
            {
                "log_file": (CRONTAB_LOG_DIR / "daily-backup.log"),
                "command": [
                    "bash",
                    str(CWD / "bin/backup-db"),
                ],
            }
        ],
    ),
]

# =========================
# EXECUTOR
# =========================


def run_cron_job(job: CronJob) -> None:
    should_run = should_run_job(
        interval=job.interval,
        file_path=job.file_path,
        max_cpu_percent=job.max_cpu_percent,
        max_ram_percent=job.max_ram_percent,
        skip_resource_checking=(job.skip_resource_checking),
        ensure_run_daily=job.ensure_run_daily,
    )

    echo_skip_or_run(
        label=job.name,
        should_run=should_run,
    )

    if not should_run:
        return

    try:
        if job.commands:
            log_command_chain(job.commands)

        if job.callback:
            job.callback()

    except Exception as exc:
        print(f"Job '{job.name}' failed: " f"{type(exc).__name__}: {exc}")

    finally:
        gc.collect()


def main() -> None:
    for job in CRON_JOBS:
        run_cron_job(job)

    gc.collect()


if __name__ == "__main__":
    main()
