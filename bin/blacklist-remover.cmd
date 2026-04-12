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
if exist venv\Scripts\activate.bat (
		call venv\Scripts\activate.bat
)

set "PY=%CWD%\venv\Scripts\python.exe"
call %PY% artisan/blacklist_remover.py

endlocal
