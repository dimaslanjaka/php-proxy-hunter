import os
import platform
import shutil
import subprocess
import sys


def is_msvc_installed():
    """
    Checks if Microsoft Visual Studio C++ (MSVC) compiler is installed on the system.

    Returns:
        bool: True if 'cl.exe' (the MSVC compiler) is found in the system PATH or in common Visual Studio directories, False otherwise.
    """
    # Check if cl.exe is in PATH
    if shutil.which("cl.exe") is not None:
        return True

    # Check common Visual Studio Build Tools installation paths
    possible_roots = [
        os.environ.get("ProgramFiles(x86)", r"C:\Program Files (x86)"),
        os.environ.get("ProgramFiles", r"C:\Program Files"),
    ]
    vs_versions = ["2022", "2019", "2017"]
    for root in possible_roots:
        for version in vs_versions:
            vs_path = os.path.join(
                root,
                "Microsoft Visual Studio",
                version,
                "BuildTools",
                "VC",
                "Tools",
                "MSVC",
            )
            if os.path.isdir(vs_path):
                for subdir in os.listdir(vs_path):
                    cl_path = os.path.join(
                        vs_path, subdir, "bin", "Hostx64", "x64", "cl.exe"
                    )
                    if os.path.isfile(cl_path):
                        return True
    return False


def check_mingw():
    """
    Checks if the MinGW (Minimalist GNU for Windows) toolchain is installed and available.

    This function attempts to determine if MinGW is present by:
    1. Running 'g++ --version' and checking if 'mingw' appears in the output.
    2. If not found, running 'pacman -Qs mingw-w64' (for MSYS2 environments) and checking the output.

    Returns:
        bool: True if MinGW is detected, False otherwise.
    """
    try:
        result = subprocess.run(
            ["g++", "--version"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )
        output = result.stdout.lower()
        if "mingw" in output:
            return True
        else:
            pacman_result = subprocess.run(
                ["pacman", "-Qs", "mingw-w64"],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
            )
            if pacman_result.stdout:
                return "mingw" in pacman_result.stdout.lower()
            else:
                return False
    except FileNotFoundError:
        return False


# Example usage
if __name__ == "__main__":
    if is_msvc_installed():
        print("MSVC is installed.")
    else:
        print("MSVC is not installed.")
