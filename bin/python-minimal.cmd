@echo off

setlocal enabledelayedexpansion

@REM delete venv directory if it exists
if exist venv (
    rmdir /s /q venv
)

py -m venv venv
call venv\Scripts\activate.bat
py -m pip install --upgrade pip
py -m pip install requests

if exist packages/proxy-checker-python (
    py -m pip install -e packages/proxy-checker-python
) else (
    py -m pip install git+https://github.com/dimaslanjaka/proxy-checker-python.git@master
)

if exist packages/proxy-hunter-python (
    py -m pip install -e packages/proxy-hunter-python
) else (
    py -m pip install "git+https://github.com/dimaslanjaka/proxy-hunter-python.git@master#subdirectory=packages/proxy-hunter-python"
)

if exist packages/rsa-utility (
    py -m pip install -e packages/rsa-utility
) else (
    py -m pip install "git+https://github.com/dimaslanjaka/php-proxy-hunter.git@master#subdirectory=packages/rsa-utility"
)
