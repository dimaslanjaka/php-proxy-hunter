import os
import subprocess
import platform


def generate_requirements():
    base_requirements = "requirements_base.txt"
    windows_specific = [
        "pywin32",
        "wmi",
        "PySide6==6.7.2",
        "QtAwesome==1.3.1",
        "Nuitka==2.4.5",
        "pyinstaller",
        "pyqtgraph",
        "pyqtdarktheme",
    ]
    linux_specific = ["uwsgi", "gunicorn"]

    try:
        lines = []
        with open(base_requirements, "r", encoding="utf-8") as base_file:
            lines.extend(base_file.readlines())

        if os.path.exists("requirements_additional.txt"):
            with open(
                "requirements_additional.txt", "r", encoding="utf-8"
            ) as base_file:
                lines.extend(base_file.readlines())

        if platform.system() == "Windows":
            lines.extend([f"\n{package}\n" for package in windows_specific])
        else:
            lines.extend([f"\n{package}\n" for package in linux_specific])

        with open("requirements.txt", "w") as req_file:
            req_file.writelines(lines)

    except IOError as e:
        print(f"Error reading or writing files: {e}")
        return False

    return True


def install_requirements():
    try:
        # Upgrade to the latest version
        subprocess.check_call(["python", "-m", "pip", "install", "--upgrade", "pip"])
        # subprocess.check_call(["pip", "install", "--upgrade", "pip"])
        # subprocess.check_call(
        #     ["python", "-m", "pip", "install", "--upgrade", "setuptools"]
        # )
        # subprocess.check_call(["python", "-m", "pip", "install", "--upgrade", "wheel"])

        # Install requirements from requirements.txt
        subprocess.check_call(["pip", "install", "-r", "requirements.txt"])
        # Install without caches
        # pip install --no-cache-dir -r requirements.txt

        # Install the socks package using PEP 517
        subprocess.check_call(["pip", "install", "socks", "--use-pep517"])

    except subprocess.CalledProcessError as e:
        print(f"Error installing requirements: {e}")
        return False

    return True


if __name__ == "__main__":
    if generate_requirements():
        if install_requirements():
            print("Installation successful!")
        else:
            print("Failed to install requirements.")
    else:
        print("Failed to generate requirements.")
