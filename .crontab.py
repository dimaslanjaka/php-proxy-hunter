#!/usr/bin/env python3

from __future__ import annotations

import os
import platform
import subprocess
import sys
import threading
import time
from pathlib import Path
from typing import Iterable, Sequence

from dotenv import load_dotenv
from src.utils.process.resources_usage import check_system_resources, get_system_usage


CWD = Path(__file__).resolve().parent
os.chdir(CWD)


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


def should_run_job(file_path: Path, interval_hours: float) -> bool:
	current_time = int(time.time())
	interval_seconds = int(interval_hours * 60 * 60)

	if file_path.is_file():
		try:
			last_fetch = int(file_path.read_text(encoding="utf-8").strip())
		except ValueError:
			last_fetch = 0

		elapsed = current_time - last_fetch
		if elapsed >= interval_seconds:
			if check_system_resources(50, 50):
				file_path.write_text(str(current_time), encoding="utf-8")
				return True
			return False
		return False

	if check_system_resources(50, 50):
		file_path.write_text(str(current_time), encoding="utf-8")
		return True
	return False


CRONTAB_STATE_DIR = CWD / "tmp/crontab"
CRONTAB_LOG_DIR = CWD / "tmp/logs/crontab"

CRONTAB_STATE_DIR.mkdir(parents=True, exist_ok=True)
CRONTAB_LOG_DIR.mkdir(parents=True, exist_ok=True)


def _finalize_log(log_file: Path, process: subprocess.Popen[bytes], command: Sequence[str]) -> None:
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

	thread = threading.Thread(target=_finalize_log, args=(log_file, process, cmd), daemon=True)
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


# run every 5 minutes
run_5m = should_run_job(CRONTAB_STATE_DIR / "5-m", 0.0833)
if run_5m:
	echo_skip_or_run("5 minutes", True)
	log_command(
		CRONTAB_LOG_DIR / "cleanup-blacklist.log",
		[PYTHON_BIN, str(CWD / "artisan/blacklist_remover.py")],
	)
else:
	echo_skip_or_run("5 minutes", False)


# run every 30 minutes
run_30m = should_run_job(CRONTAB_STATE_DIR / "30-m", 0.5)
if run_30m:
	echo_skip_or_run("30 minutes", True)
	log_command(CRONTAB_LOG_DIR / "geoip.log", ["php", "artisan/geoIp.php"])
	log_command(
		CRONTAB_LOG_DIR / "proxyCollector2.log",
		[PYTHON_BIN, "artisan/proxyCollector2.py", "--batch-size=500", "--shuffle"],
	)
	log_command(
		CRONTAB_LOG_DIR / "proxyCollector.log",
		[PYTHON_BIN, "artisan/proxyCollector.py", "--batch-size=500", "--shuffle"],
	)
	log_command(
		CRONTAB_LOG_DIR / "tun2socks-stability-check.log",
		[PYTHON_BIN, str(CWD / "artisan/proxy_tun2socks_stability.py"), "--limit=1000"],
	)
else:
	echo_skip_or_run("30 minutes", False)


# run every hour
run_1h = should_run_job(CRONTAB_STATE_DIR / "1-h", 1)
if run_1h:
	log_command(
		CRONTAB_LOG_DIR / "proxy-classifier-lookup.log",
		[PYTHON_BIN, str(CWD / "artisan/proxy-classifier-lookup.py"), "--limit=1000"],
	)
	log_command(
		CRONTAB_LOG_DIR / "filter-duplicate-ips.log",
		[
			PYTHON_BIN,
			str(CWD / "artisan/filter_duplicate_ips.py"),
			"--limit=1000",
			"--include-untested",
		],
	)
	log_command(
		CRONTAB_LOG_DIR / "proxy-socks5-checker.log",
		[PYTHON_BIN, str(CWD / "artisan/proxy_socks5_checker.py"), "--limit=100"],
	)
	log_command(
		CRONTAB_LOG_DIR / "filter_open_port.log",
		[PYTHON_BIN, str(CWD / "artisan/filter_open_port.py"), "--limit=1000"],
	)
	echo_skip_or_run("1 hour", True)
else:
	echo_skip_or_run("1 hour", False)


# run every 3 hours
run_3h = should_run_job(CRONTAB_STATE_DIR / "3-h", 3)
if run_3h:
	echo_skip_or_run("3 hours", True)
	log_command(
		CRONTAB_LOG_DIR / "proxy_checker_httpx.log",
		[PYTHON_BIN, str(CWD / "artisan/proxy_checker_httpx.py")],
	)
else:
	echo_skip_or_run("3 hours", False)


# run every 4 hours
run_4h = should_run_job(CRONTAB_STATE_DIR / "4-h", 4)
if run_4h:
	echo_skip_or_run("4 hours", True)
	proxy_fetcher_log = CRONTAB_LOG_DIR / "proxy-fetcher.log"
	with proxy_fetcher_log.open("w", encoding="utf-8") as fh:
		subprocess.Popen(
			[PYTHON_BIN, str(CWD / "proxyFetcher.py")],
			stdout=fh,
			stderr=subprocess.STDOUT,
			cwd=CWD,
			env=os.environ.copy(),
		)
else:
	echo_skip_or_run("4 hours", False)


# run every 6 hours
run_6h = should_run_job(CRONTAB_STATE_DIR / "6-h", 6)
if run_6h:
	echo_skip_or_run("6 hours", True)
else:
	echo_skip_or_run("6 hours", False)


# run every 12 hours
run_12h = should_run_job(CRONTAB_STATE_DIR / "12-h", 12)
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


# run every 24 hours
run_24h = should_run_job(CRONTAB_STATE_DIR / "24-h", 24)
if run_24h:
	echo_skip_or_run("24 hours", True)
	log_command(CRONTAB_LOG_DIR / "backup-db.log", ["bash", "-e", str(CWD / "bin/backup-db")])
	log_command(CRONTAB_LOG_DIR / "php-cleaner.log", ["php", str(CWD / "artisan/cleaner.php")])
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
else:
	echo_skip_or_run("24 hours", False)


# run every 3 days
run_72h = should_run_job(CRONTAB_STATE_DIR / "72-h", 72)
if run_72h:
	echo_skip_or_run("72 hours", True)
	log_command(
		CRONTAB_LOG_DIR / "cleanup-backups-3d.log",
		[PYTHON_BIN, str(CWD / "src/dev/backup-cleaner.py")],
	)
else:
	echo_skip_or_run("72 hours", False)


# run every week
run_168h = should_run_job(CRONTAB_STATE_DIR / "168-h", 168)
if run_168h:
	echo_skip_or_run("1 week", True)
else:
	echo_skip_or_run("1 week", False)
