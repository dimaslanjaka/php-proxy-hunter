@echo off
setlocal enabledelayedexpansion

REM Output path
set "CACHE_DIR=.cache\git"
set "OUTPUT=%CACHE_DIR%\diff.txt"

REM Ensure output directory exists
if not exist "%CACHE_DIR%" (
    mkdir "%CACHE_DIR%"
)

REM Show help if no arguments or --help/-h is passed
if "%~1"=="" (
    goto :show_help
) else if "%~1"=="--help" (
    goto :show_help
) else if "%~1"=="-h" (
    goto :show_help
)

REM Handle staged-only options
if "%~1"=="--staged-only" (
    goto :staged_only
) else if "%~1"=="-s" (
    goto :staged_only
) else if "%~1"=="-S" (
    goto :staged_only
)

REM Handle specific file diff
git --no-pager diff --cached -- "%~1" > "%OUTPUT%"
if %errorlevel%==0 (
    echo [OK] Staged diff of "%~1" saved to "%OUTPUT%"
) else (
    echo [FAIL] Failed to generate diff for "%~1"
)
exit /b

:staged_only
git --no-pager diff --staged > "%OUTPUT%"
if %errorlevel%==0 (
    echo [OK] Full staged diff saved to "%OUTPUT%"
) else (
    echo [FAIL] Failed to save staged diff
)
exit /b

:show_help
echo Git Diff Helper
echo ----------------------------
echo Usage:
echo   git-diff FILE             Show staged diff of specified file
echo   git-diff --staged-only    Show staged diff of all files
echo   git-diff -s ^| -S          Same as --staged-only
echo   git-diff --help ^| -h      Show this help message
echo.
echo Output is saved to: %OUTPUT%
exit /b
