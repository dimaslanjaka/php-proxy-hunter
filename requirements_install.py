import subprocess
import platform


def generate_requirements():
    base_requirements = "requirements_base.txt"
    windows_specific = ["pywin32", "wmi", "PySide6"]
    linux_specific = []

    with open(base_requirements, 'r') as base_file:
        lines = base_file.readlines()

    if platform.system() == 'Windows':
        lines.extend([f"{package}\n" for package in windows_specific])
    else:
        lines.extend([f"{package}\n" for package in linux_specific])

    with open('requirements.txt', 'w') as req_file:
        req_file.writelines(lines)


def install_requirements():
    subprocess.check_call(['pip', 'install', '-r', 'requirements.txt'])


if __name__ == "__main__":
    generate_requirements()
    install_requirements()
