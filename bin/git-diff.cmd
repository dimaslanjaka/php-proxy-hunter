@echo off
setlocal enabledelayedexpansion

:: Output path
set "CACHE_DIR=.cache\git"
set "OUTPUT=%CACHE_DIR%\diff.txt"

:: Ensure output directory exists
if not exist "%CACHE_DIR%" (
    mkdir "%CACHE_DIR%"
)

:: Show help if no arguments or --help is passed
if "%~1"=="" (
    goto :showHelp
)
if /I "%~1"=="--help" (
    goto :showHelp
)

:: Handle --staged-only
if /I "%~1"=="--staged-only" (
    git --no-pager diff --staged > "%OUTPUT%"
    if exist "%OUTPUT%" (
        echo [✓] Full staged diff saved to "%OUTPUT%"
    ) else (
        echo [X] Failed to save staged diff
    )
    exit /b
)

:: Handle specific file diff
set "FILE=%~1"
git --no-pager diff --cached -- "%FILE%" > "%OUTPUT%"

if exist "%OUTPUT%" (
    echo [✓] Staged diff of "%FILE%" saved to "%OUTPUT%"
) else (
    echo [X] Failed to generate diff for "%FILE%"
)

exit /b

:showHelp
echo Git Diff Helper
echo ----------------------------
echo Usage:
echo   git-diff FILE             Show staged diff of specified file
echo   git-diff --staged-only    Show staged diff of all files
echo   git-diff --help           Show this help message
echo.
echo Output is saved to: %OUTPUT%
exit /b 0
