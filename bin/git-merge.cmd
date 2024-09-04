@echo off
setlocal enabledelayedexpansion

REM Load the .env file if it exists
if exist .env (
    for /f "tokens=*" %%i in ('type .env ^| findstr /r /v "^#"') do (
        set %%i
    )
    echo .env file loaded
)

set "SCRIPT_DIR=%~dp0"
node "%SCRIPT_DIR%git-merge.js"

endlocal
