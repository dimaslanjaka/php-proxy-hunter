import os
import platform
import shutil
import subprocess
import sys


def is_msvc_installed():
    """
    Checks if Microsoft Visual Studio C++ (MSVC) compiler is installed on the system.

    Returns:
        bool: True if 'cl.exe' (the MSVC compiler) is found in the system PATH, False otherwise.
    """
    return shutil.which("cl.exe") is not None


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
