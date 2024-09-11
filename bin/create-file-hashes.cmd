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
set "SCRIPT_DIR=%~dp0"
echo Current script directory: %SCRIPT_DIR%

rem Remove trailing backslash if present
if "%SCRIPT_DIR:~-1%"=="\" set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

rem Move up one level from the script directory
for %%i in ("%SCRIPT_DIR%..") do set "CWD=%%~dpi"
set "CWD=%CWD:~0,-1%"

rem Configure the custom merge driver
git config merge.ourhashdriver.name "Custom Hash File Merge Driver"
git config merge.ourhashdriver.driver "node \"bin\create-file-hashes.js\" %%A"

REM Run the script
node "%SCRIPT_DIR%\create-file-hashes.cjs"

endlocal
