import subprocess
import platform


def generate_requirements():
    base_requirements = "requirements_base.txt"
    windows_specific = [
        "pywin32",
        "wmi",
        "PySide6",
        "nuitka",
        "pyinstaller",
        # "tensorflow",
        "pyqtgraph",
        "pyqtdarktheme",
    ]
    linux_specific = ["uwsgi", "gunicorn"]

    try:
        with open(base_requirements, "r") as base_file:
            lines = base_file.readlines()

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
        subprocess.check_call(["pip", "install", "-r", "requirements.txt"])
        subprocess.check_call(["python", "-m", "pip", "install", "--upgrade", "pip"])
        subprocess.check_call(["pip", "install", "whell"])
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
