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

:: Clear Python cache
echo Clearing Python cache...
for /r "%CWD%" %%i in (__pycache__) do (
    if exist "%%i" (
        rd /s /q "%%i"
    )
)

:: Clear pip cache
echo Clearing pip cache...
python -m pip cache purge

:: Freeze current packages to uninstall.txt
echo Freezing current packages to uninstall.txt...
@REM call python -m pip freeze > "%CWD%\uninstall.txt"
call pip freeze > "%CWD%\uninstall.txt"

:: Uninstall all packages listed in uninstall.txt
echo Uninstalling all packages...
@REM call python -m pip uninstall -r "%CWD%\uninstall.txt" -y
call pip uninstall -r "%CWD%\uninstall.txt" -y

:: Remove the uninstall.txt file
echo Removing uninstall.txt...
del "%CWD%\uninstall.txt"

:: Reinstall packages from requirements.txt
echo Reinstalling packages from requirements.txt...
call python "%CWD%\requirements_install.py"

:: Deactivate the virtual environment
call "%CWD%\venv\Scripts\deactivate"

echo Done!

endlocal
