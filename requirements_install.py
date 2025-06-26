import argparse
import importlib
import os
import platform
import re
import shutil
import subprocess
import sys
from pathlib import Path
from typing import List, Optional, Union

package_list: List[str] = []


def read_requirements_file(filepath: str) -> List[str]:
    """Read lines from a file if it exists."""
    if not os.path.exists(filepath):
        return []
    with open(filepath, encoding="utf-8") as f:
        return f.readlines()


def generate_requirements() -> bool:
    """Generate requirements.txt based on base and OS-specific packages."""
    global package_list

    lines: List[str] = []
    lines += read_requirements_file("requirements-base.txt")
    lines += read_requirements_file("requirements_additional.txt")

    dev_lines = read_requirements_file("requirements-dev.txt")
    lines += [line for line in dev_lines if line.strip() != "-r requirements.txt"]

    os_packages = {
        "Windows": [
            "pywin32",
            "wmi",
            "PySide6==6.*",
            "qtawesome==1.*",
            # "nuitka==2.*",
            # install nuitka from branch develop
            "nuitka @ https://github.com/Nuitka/Nuitka/archive/0af50da.zip",
            "pyinstaller",
            "pyqtgraph",
            "pyqtdarktheme",
            "psutil",
            "pynput",
        ],
        "Linux": [
            "uwsgi",
            "gunicorn",
        ],
    }

    lines += [f"{pkg}\n" for pkg in os_packages.get(platform.system(), [])]

    # Remove empty lines and duplicates
    package_list = list(dict.fromkeys(line for line in lines if line.strip()))

    # filter out specific packages
    def should_exclude(pkg: str) -> bool:
        # package that included from other requirements files
        if pkg.endswith(".txt") or ("-r " in pkg and ".txt" in pkg):
            return True
        # package that is a comment
        if pkg.startswith("#") or pkg.strip() == "":
            return True
        return False

    package_list = [pkg for pkg in package_list if not should_exclude(pkg)]

    with open("requirements.txt", "w", encoding="utf-8") as f:
        f.writelines(package_list)
        f.write("\n")  # Ensure newline at EOF

    return True


def is_package_installed(pkg: str) -> bool:
    """Check if a package is already installed."""
    try:
        importlib.import_module(pkg)
        return True
    except ImportError:
        return False


DEFAULT_PYPI_MIRRORS = [
    "https://pypi.org/simple",
    "https://mirrors.aliyun.com/pypi/simple/",
    "https://pypi.tuna.tsinghua.edu.cn/simple/",
    "https://mirrors.cloud.tencent.com/pypi/simple/",
    "https://repo.huaweicloud.com/repository/pypi/simple/",
    "https://mirror.nju.edu.cn/pypi/web/simple/",
]


def install_package(
    name: Union[str, List[str]],
    install_args: Optional[List[str]] = None,
    mirrors: Optional[List[str]] = None,
):
    """Attempt to install a package using pip with multiple PyPI mirror URLs."""
    if install_args is None:
        install_args = []
    if mirrors is None:
        mirrors = DEFAULT_PYPI_MIRRORS

    name_display = " ".join(name) if isinstance(name, list) else name
    print(f"Installing: {name_display}")

    base_cmd = [sys.executable, "-m", "pip", "install"]
    name_args = name if isinstance(name, list) else [name]

    for url in mirrors:
        try:
            cmd = base_cmd + name_args + [f"--index-url={url}"] + install_args
            print("\n" + " ".join(cmd) + "\n")
            subprocess.check_call(cmd)
            return
        except subprocess.CalledProcessError:
            print(f"Failed from mirror: {url}")

    raise RuntimeError(f"❌ Failed to install: {name_display}")


def install_requirements():
    """Install all packages listed in requirements.txt or regenerated list."""
    global package_list
    if not package_list:
        generate_requirements()

    subprocess.check_call(
        [
            sys.executable,
            "-m",
            "pip",
            "install",
            "--upgrade",
            "pip",
            "setuptools",
            "wheel",
        ]
    )
    install_package("socks", ["--use-pep517"])

    for raw_pkg in package_list:
        line = re.sub(r"\s+", " ", raw_pkg.strip())
        # skip comments/empty lines
        if not line or line.startswith("#"):
            continue

        if "-e " in line and not line.startswith("-e "):
            raise RuntimeError(f"❌ Unsupported requirement line: {line}")

        if "file:" in line and not line.startswith("-e "):
            local_package = re.findall(r"file:([^\s]+)", line)
            if local_package:
                pkg_path = local_package[0]
                if not os.path.exists(pkg_path):
                    raise RuntimeError(f"❌ Local package not found: {pkg_path}")
                try:
                    install_package([line])
                except Exception as e:
                    print(f"Error installing local package {pkg_path}: {e}")
                    raise
                continue

        if line.startswith("-e"):
            install_package(["-e", line.replace("-e", "").strip()])
            continue

        if " @ http" in line:
            pkgname, _ = line.split(" @ ", 1)
            if not is_package_installed(pkgname.strip()):
                install_package(line)
            continue

        if " --" in line:
            install_package([arg for arg in line.split() if arg])
            continue

        if not is_package_installed(line):
            install_package(line)


def main():
    parser = argparse.ArgumentParser(description="Manage Python requirements")
    parser.add_argument(
        "--generate", action="store_true", help="Generate requirements.txt"
    )
    parser.add_argument("--install", action="store_true", help="Install packages")

    args = parser.parse_args()

    if args.generate and not args.install:
        generate_requirements()
    elif args.install and not args.generate:
        install_requirements()
    else:
        generate_requirements()
        install_requirements()


if __name__ == "__main__":
    main()
