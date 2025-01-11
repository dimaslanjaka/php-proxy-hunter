@echo off
REM Get the directory of the current Batch script
SET SCRIPT_DIR=%~dp0
SET SCRIPT_DIR=%SCRIPT_DIR:~0,-1%
ECHO Current script directory: %SCRIPT_DIR%

REM Set CWD to the parent directory of the script's directory
FOR %%I IN ("%SCRIPT_DIR%") DO SET CWD=%%~dpI
SET CWD=%CWD:~0,-1%
ECHO Current working directory: %CWD%

:: Fix empty production variables
IF NOT EXIST "%CWD%\.env.build.json" (
    echo {} > "%CWD%\.env.build.json"
    echo File %CWD%\.env.build.json created.
) ELSE (
    echo File %CWD%\.env.build.json already exists.
)

REM Run the Node.js script
node "%SCRIPT_DIR%\build-project.mjs"
