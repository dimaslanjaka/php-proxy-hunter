import argparse
import importlib
import os
import platform
import re
import subprocess
from typing import List, Union

package_list: List[str] = []


def generate_requirements():
    global package_list
    base_requirements = "requirements_base.txt"
    windows_specific = [
        "pywin32",
        "wmi",
        "PySide6==6.*",
        "qtawesome==1.*",
        # "nuitka==2.*",
        "nuitka @ https://github.com/Nuitka/Nuitka/archive/develop.zip",
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
    package_list = unique_lines

    # Write to requirements.txt
    with open("requirements.txt", "w", encoding="utf-8") as req_file:
        req_file.writelines(unique_lines)

    # Add a new line at the end of the file
    with open("requirements.txt", "a", encoding="utf-8") as req_file:
        req_file.write("\n")

    return True


def is_package_installed(package_name):
    try:
        importlib.import_module(package_name)
        return True
    except ImportError:
        return False


def install_package(name: Union[str, List[str]], install_args=[]):
    if isinstance(name, str):
        print(f"installing {name}")
    else:
        print(f"installing local package \"{' '.join(name)}\"")
    index_urls = [
        "https://pypi.org/simple",
        "https://mirrors.sustech.edu.cn/pypi/simple/",
        "https://mirrors.sustech.edu.cn/pypi/web/simple",
        "https://pypi.tuna.tsinghua.edu.cn/simple/",
        "https://mirrors.bfsu.edu.cn/pypi/web/simple/",
        "https://mirrors.aliyun.com/pypi/simple/",
        "https://mirrors.cloud.tencent.com/pypi/simple/",
        "https://repo.huaweicloud.com/repository/pypi/simple/",
        "https://mirror.nju.edu.cn/pypi/web/simple/",
    ]
    for index_url in index_urls:
        try:
            if isinstance(name, str):
                subprocess.check_call(
                    [
                        "pip",
                        "install",
                        name,
                        f"--index-url={index_url}",
                    ]
                    + install_args
                )
            else:
                # param name is list
                subprocess.check_call(
                    ["pip", "install"]
                    + name
                    + [f"--index-url={index_url}"]
                    + install_args
                )
            break
        except Exception:
            print(f"fail install {name} from {index_url}")


def install_requirements():
    global package_list
    if not package_list:
        generate_requirements()
    subprocess.check_call(
        ["python", "-m", "pip", "install", "--upgrade", "pip", "setuptools", "wheel"]
    )
    if not is_package_installed("socks"):
        install_package("socks", ["--use-pep517"])
    for pkg in package_list:
        name = re.sub(r"\s+", " ", pkg.strip()).strip()
        if name.startswith("#") or not name:
            # skip comment block & empty package name
            continue
        if name.startswith("-e"):
            # always install local package
            name = name.replace("-e ", "").strip()
            install_package(["-e", name])
        elif "@ http" in name:
            # install `packagename @ url-zip`
            pkgname, url = name.split(" @ ")
            if not is_package_installed(pkgname.strip()):
                install_package(name)
        elif " --" in name:
            _args = [item for item in name.split(" ") if item]
            print(_args)
            install_package(_args)
        elif not is_package_installed(pkg):
            install_package(name)

    # try:
    #     subprocess.check_call(["pip", "install", "-r", "requirements.txt"])
    #     subprocess.check_call(["pip", "install", "socks", "--use-pep517"])
    # except Exception:
    #     pass


if __name__ == "__main__":
    # Set up argument parser
    parser = argparse.ArgumentParser()
    parser.add_argument("--generate", action="store_true", help="Generate requirements")
    parser.add_argument("--install", action="store_true", help="Install requirements")

    # Parse arguments
    args = parser.parse_args()

    # Run actions based on arguments
    if args.generate and not args.install:
        generate_requirements()
    elif args.install and not args.generate:
        install_requirements()
    else:
        generate_requirements()
        install_requirements()
