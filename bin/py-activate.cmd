@echo off
REM Create or activate virtual environment from <project>\venv

REM Get directory of this script (i.e., <project>\bin)
set "SCRIPT_DIR=%~dp0"

REM Go up one level to get to <project> root
pushd "%SCRIPT_DIR%\.."
set "PROJECT_ROOT=%cd%"
set "VENV_DIR=%PROJECT_ROOT%\venv"

REM Check if venv exists
if not exist "%VENV_DIR%" (
    echo Virtual environment not found. Creating at: %VENV_DIR%
    python -m venv "%VENV_DIR%"
    if errorlevel 1 (
        echo Failed to create virtual environment.
        popd
        exit /b 1
    )
    echo Virtual environment created.
) else (
    echo Virtual environment already exists. Activating...
)

REM Activate the virtual environment
call "%VENV_DIR%\Scripts\activate.bat"

REM Return to original directory
popd
