import os
import psutil


def count_running_files(
    match_name: str = "proxyCollector.py",
    verbose: bool = False,
    venv_only: bool = False,
) -> int:
    """
    Count other running Python interpreter processes executing `match_name`.

    Fixes Windows `py.exe` launcher double-counting.
    """

    current_pid = os.getpid()
    target = os.path.basename(match_name).lower()
    # If venv_only is requested, compute project venv path (repo root is three dirs up)
    venv_dir = None
    if venv_only:
        repo_root = os.path.abspath(
            os.path.join(os.path.dirname(__file__), "..", "..", "..")
        )
        venv_dir = os.path.abspath(os.path.join(repo_root, "venv")).lower()
    pids = set()

    if verbose:
        print(f"Counting running processes matching '{target}'...")

    for proc in psutil.process_iter(["pid", "cmdline", "name", "exe"]):
        try:
            info = proc.info
            pid = info.get("pid")
            if not pid or pid == current_pid:
                continue

            name = (info.get("name") or "").lower()
            exe = (info.get("exe") or "").lower()
            cmdline_list = info.get("cmdline") or []
            joined = " ".join(cmdline_list).lower()

            if target not in joined:
                continue

            # If venv_only is requested, only enforce venv membership for
            # Python interpreter processes (name or exe containing 'python').
            if venv_only:
                exe_path = str(info.get("exe") or "").lower()
                is_python_interp = ("python" in name) or ("python" in exe_path)
                if not is_python_interp:
                    # allow non-Python processes regardless of exe path
                    pids.add(pid)
                    if verbose:
                        print(f"  Non-Python process allowed PID {pid}: {joined}")
                    continue

                # For Python interpreters, require the interpreter executable to be inside project venv
                if not exe_path and cmdline_list:
                    exe_path = str(cmdline_list[0]).lower()
                if not exe_path or not (venv_dir and exe_path.startswith(venv_dir)):
                    if verbose:
                        print(
                            f"  Skipping PID {pid} (interp exe not in venv): {exe_path}"
                        )
                    continue

            pids.add(pid)
            if verbose:
                print(f"  Name: {name}, Exe: {exe}")
                print(f"  Found matching process PID {pid}: {joined}")

        except (psutil.NoSuchProcess, psutil.AccessDenied):
            continue

    result = len(pids)
    if verbose:
        print(f"Total matching processes (excluding current): {result}")
    return result


if __name__ == "__main__":
    match = "proxyCollector.py"
    count_running_files(match_name=match, verbose=True, venv_only=True)
