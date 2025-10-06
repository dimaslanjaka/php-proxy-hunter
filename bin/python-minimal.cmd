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
py -m pip install -e packages/proxy-checker-python
py -m pip install -e packages/proxy-hunter-python
py -m pip install -e packages/rsa-utility
