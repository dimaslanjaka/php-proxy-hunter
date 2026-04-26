import psutil
import time
import os
import sys
from datetime import datetime

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../../..")))

from src.func_console import red, yellow, green


def bytes_to_mb(b):
    return b / (1024 * 1024)


def normalize_cmd(cmd: str) -> str:
    # Fix Windows NT path prefixes
    if cmd.startswith("\\??\\") or cmd.startswith("\\\\?\\"):
        return cmd[4:]
    return cmd


def color_percent_value_text(value: float, text: str) -> str:
    """Return colored `text` according to `value` thresholds."""
    if value >= 70:
        return red(text)
    if value >= 30:
        return yellow(text)
    return green(text)


def main():
    total_ram = psutil.virtual_memory().total
    current_pid = os.getpid()
    parent_pid = os.getppid()
    script_name = os.path.basename(__file__).lower()

    # Warm-up CPU measurement
    procs = []
    for p in psutil.process_iter(["pid", "name", "cmdline"]):
        try:
            p.cpu_percent(None)
            procs.append(p)
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            continue

    time.sleep(0.2)

    results = []

    for p in procs:
        try:
            # Ignore self + parent
            if p.pid in (current_pid, parent_pid):
                continue

            name = p.name().lower()

            # Ignore system / UI noise
            if name in (
                "system idle process",
                "system",
                "code.exe",
                "explorer.exe",
                "firefox.exe",
                "chrome.exe",
                "msedge.exe",
                "brave.exe",
                "crashhelper.exe",
            ):
                continue

            cmdline = p.cmdline()
            cmd = " ".join(cmdline) if cmdline else p.name()
            cmd = normalize_cmd(cmd)

            # Ignore this script
            if script_name in cmd.lower():
                continue

            cpu = p.cpu_percent(None)

            mem = p.memory_info().rss
            mem_mb = bytes_to_mb(mem)
            mem_percent = (mem / total_ram) * 100

            results.append((cpu, mem_mb, mem_percent, cmd))

        except (psutil.NoSuchProcess, psutil.AccessDenied):
            continue

    # Sort by highest CPU usage
    results.sort(key=lambda x: x[0], reverse=True)

    # Output
    print(f"\n=== {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} ===")

    for cpu, mem_mb, mem_percent, cmd in results[:10]:
        cpu_s = color_percent_value_text(cpu, f"{cpu:.2f}%")
        ram_s = color_percent_value_text(mem_percent, f"{mem_percent:.2f}%")

        print(f"{cmd} | " f"CPU {cpu_s} | " f"RAM {mem_mb:.2f}MB ({ram_s})")


if __name__ == "__main__":
    main()
