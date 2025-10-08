
@echo off

setlocal EnableDelayedExpansion

REM Get the directory of the batch file
set "SCRIPT_DIR=%~dp0"

REM Remove the trailing backslash
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Move up one levels from the script directory
for %%i in ("%SCRIPT_DIR%") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

REM add bin to PATH
set "PATH=%CWD%\bin;%PATH%"

REM Try to find python executable to create the venv: prefer python3, then python
set PY_CREATOR=
for %%P in (python3 python) do (
    where %%P >nul 2>nul && if not defined PY_CREATOR set PY_CREATOR=%%P
)

if not defined PY_CREATOR (
    echo Python is not installed or not on PATH.& echo Please install Python 3 and retry.
    exit /b 1
)

@REM create venv if not exists
if not exist venv (
  %PY_CREATOR% -m venv venv
)
call venv\Scripts\activate.bat
py -m pip install --upgrade pip
py -m pip install requests PySocks beautifulsoup4 lxml httpx

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

endlocal
