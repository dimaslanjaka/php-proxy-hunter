import os
import subprocess
import platform


def generate_requirements():
    base_requirements = "requirements_base.txt"
    windows_specific = [
        "pywin32",
        "wmi",
        "PySide6",
        "QtAwesome",
        "Nuitka",
        "pyinstaller",
        "pyqtgraph",
        "pyqtdarktheme",
        "psutil",
        "pynput",
    ]
    linux_specific = ["uwsgi", "gunicorn"]

    lines = []
    with open(base_requirements, "r", encoding="utf-8") as base_file:
        lines.extend(base_file.readlines())

    if os.path.exists("requirements_additional.txt"):
        with open("requirements_additional.txt", "r", encoding="utf-8") as base_file:
            lines.extend(base_file.readlines())

    if platform.system() == "Windows":
        lines.extend([f"{package}\n" for package in windows_specific])
    else:
        lines.extend([f"{package}\n" for package in linux_specific])

    # Remove empty lines and duplicates
    unique_lines = list(dict.fromkeys([line for line in lines if line.strip()]))

    # Write to requirements.txt
    with open("requirements.txt", "w", encoding="utf-8") as req_file:
        req_file.writelines(unique_lines)

    # Add a new line at the end of the file
    with open("requirements.txt", "a", encoding="utf-8") as req_file:
        req_file.write("\n")

    return True


def install_requirements():
    subprocess.check_call(
        [
            "python",
            "-m",
            "pip",
            "install",
            "--upgrade",
            "pip",
            "setuptools",
            "wheel",
        ]
    )

    subprocess.check_call(["pip", "install", "-r", "requirements.txt"])

    subprocess.check_call(["pip", "install", "socks", "--use-pep517"])

    return True


if __name__ == "__main__":
    if generate_requirements():
        if install_requirements():
            print("Installation successful!")
        else:
            print("Failed to install requirements.")
    else:
        print("Failed to generate requirements.")
