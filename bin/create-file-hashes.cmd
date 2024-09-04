@echo off
setlocal enabledelayedexpansion

REM Load the .env file if it exists
if exist .env (
    for /f "tokens=*" %%i in ('type .env ^| findstr /r /v "^#"') do (
        set %%i
    )
    echo .env file loaded
)

rem Get the directory of the current script
set SCRIPT_DIR=%~dp0
rem Remove trailing backslash
set SCRIPT_DIR=%SCRIPT_DIR:~0,-1%
echo Current script directory: %SCRIPT_DIR%

REM Move up two levels from the script directory
for %%i in ("%SCRIPT_DIR%") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

node "%SCRIPT_DIR%create-file-hashes.js"

endlocal
