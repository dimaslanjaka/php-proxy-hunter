import psutil
import time
import os
import sys
from datetime import datetime
from colorama import init, Fore, Style

# Always enable color
USE_COLOR = True
# Preserve ANSI sequences when stdout is redirected
init(autoreset=True, strip=False, convert=False)


def bytes_to_mb(b):
    return b / (1024 * 1024)


def normalize_cmd(cmd: str) -> str:
    # Fix Windows NT path prefixes
    if cmd.startswith("\\??\\") or cmd.startswith("\\\\?\\"):
        return cmd[4:]
    return cmd


def color_percent(value):
    if not USE_COLOR:
        return ""
    if value >= 70:
        return Fore.RED
    elif value >= 30:
        return Fore.YELLOW
    else:
        return Fore.GREEN


def reset():
    return Style.RESET_ALL if USE_COLOR else ""


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
        cpu_c = color_percent(cpu)
        ram_c = color_percent(mem_percent)
        r = reset()

        print(
            f"{cmd} | "
            f"CPU {cpu_c}{cpu:.2f}%{r} | "
            f"RAM {mem_mb:.2f}MB ({ram_c}{mem_percent:.2f}%{r})"
        )


if __name__ == "__main__":
    main()
