@echo off
REM Get the directory of the script
SET SCRIPT_DIR=%~dp0

REM Remove trailing backslash from SCRIPT_DIR if present
SET SCRIPT_DIR=%SCRIPT_DIR:~0,-1%

REM Set the path to your git repository from parent folder
FOR %%I IN ("%SCRIPT_DIR%") DO SET CWD=%%~dpI

REM Parse and export .env file (dotenv)
IF EXIST "%CWD%.env" (
    FOR /F "usebackq tokens=1,* delims==" %%A IN (`findstr /V /R "^#" "%CWD%.env"`) DO SET %%A=%%B
)

REM Run git diff with any provided arguments
git --no-pager diff %*
