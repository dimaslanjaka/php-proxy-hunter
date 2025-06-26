@echo off
REM Create or activate virtual environment from <project>\venv

REM Get directory of this script (i.e., <project>\bin)
set "SCRIPT_DIR=%~dp0"

REM Go up one level to get to <project> root
pushd "%SCRIPT_DIR%\.."
set "PROJECT_ROOT=%cd%"
set "VENV_DIR=%PROJECT_ROOT%\venv"
set "VENV_SCRIPTS=%VENV_DIR%\Scripts"

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
    echo Virtual environment already exists.
)

REM Ensure python3.exe exists in Scripts
if exist "%VENV_SCRIPTS%\python.exe" (
    if not exist "%VENV_SCRIPTS%\python3.exe" (
        echo Creating python3.exe shim...
        copy "%VENV_SCRIPTS%\python.exe" "%VENV_SCRIPTS%\python3.exe" >nul
        echo python3.exe shim created.
    ) else (
        echo python3.exe already exists.
    )
) else (
    echo ERROR: python.exe not found in venv Scripts directory.
)

REM Activate the virtual environment
call "%VENV_SCRIPTS%\activate.bat"

REM Return to original directory
popd
