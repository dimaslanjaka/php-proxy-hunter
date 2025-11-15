@echo off

@REM Script to fix PySide6 installation in the existing venv

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

@REM Reinstall PySide6 to fix any installation issues

set "PY=%CWD%\venv\Scripts\python.exe"
call %PY% -m pip install --force-reinstall PySide6==6.* qtawesome==1.* pywin32 wmi pyqtdarktheme numpy==2.1.0 pynput "nuitka @ https://github.com/Nuitka/Nuitka/archive/0af50da.zip"
call %PY% -m pip install --force-reinstall markdown jinja2 opencv-python requests pebble pypdl
