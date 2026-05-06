from __future__ import annotations

import ctypes
import platform
import subprocess
import time
from pathlib import Path
import shutil
import atexit
import os, sys, psutil

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../..")))

from src.func_console import color_percent_value_text


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


def check_system_resources(
    max_cpu_percent: int = 50, max_ram_percent: int = 50
) -> bool:
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


def get_storage_usage(path: str | None = None) -> int | None:
    try:
        if path is None:
            # choose filesystem root for current working directory
            path = Path.cwd().anchor

        if psutil is not None:
            try:
                usage = psutil.disk_usage(path)
                return int(usage.percent)
            except Exception:
                pass

        total, used, free = shutil.disk_usage(path)
        if total > 0:
            return int((100 * used) / total)
    except Exception:
        return None

    return None


def display_system_usage(
    sample_cpu_seconds: float = 0.5, storage_path: str | None = None, json: bool = False
) -> None | dict:
    cpu_usage, ram_usage = get_system_usage(sample_cpu_seconds=sample_cpu_seconds)
    storage_usage = get_storage_usage(storage_path)

    # attempt to get byte-level RAM and storage info for human-readable display
    ram_percent: int | None = None
    ram_used_bytes: int | None = None
    ram_total_bytes: int | None = None

    storage_percent: int | None = None
    storage_used_bytes: int | None = None
    storage_total_bytes: int | None = None

    # RAM info
    try:
        if psutil is not None:
            mem = psutil.virtual_memory()
            ram_percent = int(mem.percent)
            ram_total_bytes = int(mem.total)
            ram_used_bytes = int(mem.total - getattr(mem, "available", 0))
        else:
            # try /proc/meminfo on Linux
            proc = Path("/proc/meminfo")
            if proc.is_file():
                mem_total = None
                mem_available = None
                for line in proc.read_text(
                    encoding="utf-8", errors="ignore"
                ).splitlines():
                    if line.startswith("MemTotal:"):
                        mem_total = int(line.split()[1])
                    elif line.startswith("MemAvailable:"):
                        mem_available = int(line.split()[1])
                if mem_total and mem_available is not None:
                    ram_total_bytes = mem_total * 1024
                    ram_used_bytes = (mem_total - mem_available) * 1024
                    ram_percent = int((100 * (mem_total - mem_available)) / mem_total)
            else:
                # try Windows GlobalMemoryStatusEx
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
                    if ctypes.windll.kernel32.GlobalMemoryStatusEx(
                        ctypes.byref(status)
                    ):
                        ram_total_bytes = int(status.ullTotalPhys)
                        ram_used_bytes = int(status.ullTotalPhys - status.ullAvailPhys)
                        ram_percent = int(status.dwMemoryLoad)
                except Exception:
                    pass
    except Exception:
        ram_percent = ram_usage

    # Storage info
    try:
        target = storage_path if storage_path is not None else Path.cwd().anchor
        if psutil is not None:
            du = psutil.disk_usage(target)
            storage_percent = int(du.percent)
            storage_total_bytes = int(du.total)
            storage_used_bytes = int(du.used)
        else:
            total, used, free = shutil.disk_usage(target)
            storage_total_bytes = int(total)
            storage_used_bytes = int(used)
            if total > 0:
                storage_percent = int((100 * used) / total)
    except Exception:
        storage_percent = storage_usage

    def _fmt_bytes(b: int | None) -> str:
        if b is None:
            return "?"
        step = 1024.0
        units = ["B", "KB", "MB", "GB", "TB", "PB"]
        val = float(b)
        idx = 0
        while val >= step and idx < len(units) - 1:
            val /= step
            idx += 1
        # show one decimal for MB+ or integer for bytes
        if units[idx] in {"MB", "GB", "TB", "PB"}:
            return f"{val:.1f}{units[idx]}"
        return f"{int(val)}{units[idx]}"

    parts: list[str] = []
    human_parts: list[str] = []
    # CPU
    if cpu_usage is not None:
        parts.append(
            f"CPU={color_percent_value_text(cpu_usage, f'{cpu_usage}%', True)}"
        )
        human_parts.append(f"CPU={cpu_usage}%")
    else:
        parts.append("CPU=?")
        human_parts.append("CPU=?")

    # RAM
    if (
        ram_percent is not None
        and ram_total_bytes is not None
        and ram_used_bytes is not None
    ):
        parts.append(
            f"RAM={color_percent_value_text(ram_percent, f'{ram_percent}%', True)} ({_fmt_bytes(ram_used_bytes)}/{_fmt_bytes(ram_total_bytes)})"
        )
        human_parts.append(
            f"RAM={ram_percent}% ({_fmt_bytes(ram_used_bytes)}/{_fmt_bytes(ram_total_bytes)})"
        )
    else:
        if ram_usage is not None:
            parts.append(
                f"RAM={color_percent_value_text(ram_usage, f'{ram_usage}%', True)}"
            )
            human_parts.append(f"RAM={ram_usage}%")
        else:
            parts.append("RAM=?")
            human_parts.append("RAM=?")

    # Storage
    if (
        storage_percent is not None
        and storage_total_bytes is not None
        and storage_used_bytes is not None
    ):
        parts.append(
            f"Storage={color_percent_value_text(storage_percent, f'{storage_percent}%', True)} ({_fmt_bytes(storage_used_bytes)}/{_fmt_bytes(storage_total_bytes)})"
        )
        human_parts.append(
            f"Storage={storage_percent}% ({_fmt_bytes(storage_used_bytes)}/{_fmt_bytes(storage_total_bytes)})"
        )
    else:
        if storage_usage is not None:
            parts.append(
                f"Storage={color_percent_value_text(storage_usage, f'{storage_usage}%', True)}"
            )
            human_parts.append(f"Storage={storage_usage}%")
        else:
            parts.append("Storage=?")
            human_parts.append("Storage=?")

    if json:
        # build a machine-readable summary
        return {
            "cpu_usage": cpu_usage,
            "ram_percent": ram_percent,
            "ram_used_bytes": ram_used_bytes,
            "ram_total_bytes": ram_total_bytes,
            "storage_percent": storage_percent,
            "storage_used_bytes": storage_used_bytes,
            "storage_total_bytes": storage_total_bytes,
            "human": ", ".join(human_parts),
        }

    print("System usage summary at exit: " + ", ".join(parts))


if __name__ == "__main__":
    # Register a short summary to be displayed when the interpreter exits normally.
    # Uses a small sample interval to avoid long blocking on exit.
    atexit.register(display_system_usage, 0.1)
