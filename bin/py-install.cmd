@echo off
setlocal

REM Get the directory of the batch file
set "SCRIPT_DIR=%~dp0"

REM Remove the trailing backslash
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

REM Move up one levels from the script directory
for %%i in ("%SCRIPT_DIR%") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

REM Output the current working directory
echo Working directory: %CWD%

REM Check if the venv folder exists
if not exist "%CWD%\venv" (
    echo Creating virtual environment...
    call pip install virtualenv
    call python -m venv "%CWD%\venv"
) else (
    echo Virtual environment already exists.
)

:: Activate the virtual environment
call "%CWD%\venv\Scripts\activate"

REM Install requirements
call python "%CWD%\requirements_install.py"

:: Deactivate the virtual environment
call "%CWD%\venv\Scripts\deactivate"

@REM Uncomment the line below if you need to upgrade pip
REM "%CWD%\venv\Scripts\python.exe" -m pip install --upgrade pip

endlocal
