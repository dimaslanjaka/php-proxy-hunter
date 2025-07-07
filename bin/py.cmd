@echo off
setlocal enabledelayedexpansion

REM Get the directory of the batch file
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Move up one level to get <project> root
for %%i in ("%SCRIPT_DIR%") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

REM Prefer .venv over venv for virtual environment path
set ".VENV_DIR=%CWD%\.venv"
set "VENV_DIR=%CWD%\venv"
set "VENV_PATH=%VENV_DIR%"
if exist "%CWD%\.venv" (
    set "VENV_PATH=%CWD%\.venv"
)
set "VENV_SCRIPTS=%VENV_PATH%\Scripts"

REM Check if venv exists
if not exist "%VENV_PATH%" (
    echo Creating virtual environment...
    call python -m venv "%VENV_PATH%"
    if errorlevel 1 (
        echo Failed to create virtual environment.
        exit /b 1
    )
    echo Virtual environment created.
) else (
    echo Virtual environment already exists.
)

REM Ensure python3.exe shim exists
if exist "%VENV_SCRIPTS%\python.exe" (
    if not exist "%VENV_SCRIPTS%\python3.exe" (
        echo Creating python3.exe shim...
        copy "%VENV_SCRIPTS%\python.exe" "%VENV_SCRIPTS%\python3.exe" >nul
        echo python3.exe shim created.
    )
)

REM Activate the virtual environment
call "%VENV_SCRIPTS%\activate.bat"

REM Run python with all forwarded arguments
call python %*

endlocal
