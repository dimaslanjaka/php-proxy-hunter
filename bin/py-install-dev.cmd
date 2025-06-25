@echo off
setlocal

REM Get the directory of the batch file
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Move up one level from script directory
for %%i in ("%SCRIPT_DIR%") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

set "VENV_DIR=%CWD%\venv"

REM Check if Python is using the venv
for /f "delims=" %%i in ('python -c "import sys; print(sys.prefix)"') do set PY_PREFIX=%%i

echo %PY_PREFIX% | findstr /i "%VENV_DIR%" >nul
if %errorlevel% neq 0 (
    echo Virtual environment is not active. Checking if it exists...

    if not exist "%VENV_DIR%\Scripts\activate.bat" (
        echo Creating virtual environment at: %VENV_DIR%
        python -m venv "%VENV_DIR%"
        if %errorlevel% neq 0 (
            echo Failed to create virtual environment.
            exit /b %errorlevel%
        )
    )

    call "%VENV_DIR%\Scripts\activate.bat"
)

pip install -r "%CWD%\requirements-dev.txt"
if %errorlevel% neq 0 (
    echo Failed to install development requirements.
    exit /b %errorlevel%
)

echo Development requirements installed successfully.
endlocal
