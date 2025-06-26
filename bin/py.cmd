@echo off
setlocal enabledelayedexpansion

REM Get the directory of the batch file
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Move up one level to get <project> root
for %%i in ("%SCRIPT_DIR%") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

echo Working directory: %CWD%

REM Set venv path
set "VENV_DIR=%CWD%\venv"
set "VENV_SCRIPTS=%VENV_DIR%\Scripts"

REM Check if venv exists
if not exist "%VENV_DIR%" (
    echo Creating virtual environment...
    REM Ensure virtualenv is available (optional, not required for `python -m venv`)
    call python -m venv "%VENV_DIR%"
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

REM Optional: Upgrade pip
REM call python -m pip install --upgrade pip

endlocal
