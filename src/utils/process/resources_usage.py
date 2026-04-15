from __future__ import annotations

import ctypes
import platform
import subprocess
import time
from pathlib import Path

try:
	import psutil  # type: ignore[import-not-found]
except Exception:
	psutil = None


def _read_linux_cpu_percent(sample_seconds: float = 0.5) -> int | None:
	proc_stat = Path("/proc/stat")
	if not proc_stat.is_file():
		return None

	first_line = proc_stat.read_text(encoding="utf-8", errors="ignore").splitlines()
	if not first_line:
		return None
	first = first_line[0].split()

	time.sleep(sample_seconds)

	second_line = proc_stat.read_text(encoding="utf-8", errors="ignore").splitlines()
	if not second_line:
		return None
	second = second_line[0].split()

	if len(first) < 9 or len(second) < 9:
		return None

	values_a = [int(x) for x in first[1:9]]
	values_b = [int(x) for x in second[1:9]]
	idle_delta = (values_b[3] + values_b[4]) - (values_a[3] + values_a[4])
	total_delta = sum(values_b) - sum(values_a)
	if total_delta <= 0:
		return None

	return int((100 * (total_delta - idle_delta)) / total_delta)


def _read_linux_ram_percent() -> int | None:
	proc_meminfo = Path("/proc/meminfo")
	if not proc_meminfo.is_file():
		return None

	mem_total = None
	mem_available = None
	for line in proc_meminfo.read_text(encoding="utf-8", errors="ignore").splitlines():
		if line.startswith("MemTotal:"):
			mem_total = int(line.split()[1])
		elif line.startswith("MemAvailable:"):
			mem_available = int(line.split()[1])

	if mem_total and mem_available is not None and mem_total > 0:
		return int((100 * (mem_total - mem_available)) / mem_total)
	return None


def _read_windows_cpu_percent() -> int | None:
	try:
		result = subprocess.run(
			["wmic", "cpu", "get", "LoadPercentage", "/value"],
			capture_output=True,
			text=True,
			timeout=5,
			check=False,
		)
		for line in result.stdout.splitlines():
			if line.strip().startswith("LoadPercentage="):
				value = line.split("=", 1)[1].strip()
				if value.isdigit():
					return int(value)
	except Exception:
		return None

	return None


def _read_windows_ram_percent() -> int | None:
	class MemoryStatusEx(ctypes.Structure):
		_fields_ = [
			("dwLength", ctypes.c_ulong),
			("dwMemoryLoad", ctypes.c_ulong),
			("ullTotalPhys", ctypes.c_ulonglong),
			("ullAvailPhys", ctypes.c_ulonglong),
			("ullTotalPageFile", ctypes.c_ulonglong),
			("ullAvailPageFile", ctypes.c_ulonglong),
			("ullTotalVirtual", ctypes.c_ulonglong),
			("ullAvailVirtual", ctypes.c_ulonglong),
			("ullAvailExtendedVirtual", ctypes.c_ulonglong),
		]

	try:
		status = MemoryStatusEx()
		status.dwLength = ctypes.sizeof(MemoryStatusEx)
		if ctypes.windll.kernel32.GlobalMemoryStatusEx(ctypes.byref(status)):
			return int(status.dwMemoryLoad)
	except Exception:
		return None

	return None


def get_system_usage(sample_cpu_seconds: float = 0.5) -> tuple[int | None, int | None]:
	cpu_usage: int | None = None
	ram_usage: int | None = None

	if psutil is not None:
		try:
			cpu_usage = int(psutil.cpu_percent(interval=sample_cpu_seconds))
		except Exception:
			cpu_usage = None
		try:
			ram_usage = int(psutil.virtual_memory().percent)
		except Exception:
			ram_usage = None

	if cpu_usage is None or ram_usage is None:
		os_name = platform.system()
		if os_name in {"Darwin", "Linux"}:
			if cpu_usage is None:
				cpu_usage = _read_linux_cpu_percent(sample_cpu_seconds)
			if ram_usage is None:
				ram_usage = _read_linux_ram_percent()
		elif os_name == "Windows":
			if cpu_usage is None:
				cpu_usage = _read_windows_cpu_percent()
			if ram_usage is None:
				ram_usage = _read_windows_ram_percent()

	return cpu_usage, ram_usage


def check_system_resources(max_cpu_percent: int = 50, max_ram_percent: int = 50) -> bool:
	cpu_usage, ram_usage = get_system_usage(sample_cpu_seconds=1.0)

	if cpu_usage is not None and ram_usage is not None:
		if cpu_usage <= max_cpu_percent and ram_usage <= max_ram_percent:
			return True
		print(
			"Skipping job due to high resource usage: "
			f"CPU={cpu_usage}% (max {max_cpu_percent}%), "
			f"RAM={ram_usage}% (max {max_ram_percent}%)."
		)
		return False

	# If usage cannot be determined on this platform, do not block execution.
	return True

