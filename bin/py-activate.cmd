@echo off
REM Activate virtual environment from <project>/venv

REM Get directory of this script (i.e., <project>\bin)
set "SCRIPT_DIR=%~dp0"
REM Go up one level to get to <project> root
pushd "%SCRIPT_DIR%\.."

REM Activate the virtual environment
call "venv\Scripts\activate.bat"

REM Return to original directory
popd
