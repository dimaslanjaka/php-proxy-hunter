import os
import subprocess
import platform


def generate_requirements():
    base_requirements = "requirements_base.txt"
    windows_specific = [
        "pywin32",
        "wmi",
        "pyside6==6.*",
        "qtawesome==1.*",
        "nuitka==2.*",
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
    index_urls = [
        "https://pypi.org/simple",
        "https://mirrors.sustech.edu.cn/pypi/web/simple",
        "https://pypi.tuna.tsinghua.edu.cn/simple/",
        "https://mirrors.bfsu.edu.cn/pypi/web/simple/",
        "https://mirrors.aliyun.com/pypi/simple/",
        "https://pypi.douban.com/simple/",
        "https://mirror.baidu.com/pypi/simple/",
        "https://mirrors.cloud.tencent.com/pypi/simple/",
        "https://repo.huaweicloud.com/repository/pypi/simple/",
        "https://mirror.nju.edu.cn/pypi/web/simple/",
        "https://mirrors.sustech.edu.cn/pypi/simple/",
    ]
    index_urls = list(set(index_urls))  # unique

    last_exception = None

    for index_url in index_urls:
        try:
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
                    f"--index-url={index_url}",
                ]
            )
            subprocess.check_call(
                ["pip", "install", "-r", "requirements.txt", f"--index-url={index_url}"]
            )
            subprocess.check_call(
                ["pip", "install", "socks", "--use-pep517", f"--index-url={index_url}"]
            )
            return True  # Exit function if both installs succeed
        except subprocess.CalledProcessError as e:
            last_exception = e
            # Continue with the next index URL
            continue
        except Exception as e:
            last_exception = e
            print(f"An unexpected error occurred: {e}")
            break

    # If all index URLs fail, raise the last exception encountered
    if last_exception:
        raise last_exception

    return False


if __name__ == "__main__":
    if generate_requirements():
        if install_requirements():
            print("Installation successful!")
        else:
            print("Failed to install requirements.")
    else:
        print("Failed to generate requirements.")
