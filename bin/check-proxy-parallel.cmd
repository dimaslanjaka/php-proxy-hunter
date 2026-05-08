@echo off

REM Number of instances to run in parallel
set NUM_INSTANCES=5
set CWD=%~dp0
set CWD=%CWD:~0,-1%
set PROJECT_DIR=%CWD%\..

set CUSTOM_PHP_PATH="%PROJECT_DIR%\assets\php\php.exe"

rem Check if custom PHP exists, if yes, set it as PHP_PATH
if exist "%CUSTOM_PHP_PATH%" (
    set PHP_PATH="%CUSTOM_PHP_PATH%"
) else (
    set PHP_PATH=php
)

set PYTHON_PATH=python
if exist "%PROJECT_DIR%\venv\Scripts\python.exe" (
    set PYTHON_PATH="%PROJECT_DIR%\venv\Scripts\python.exe"
)

echo "CWD %PROJECT_DIR%"

REM Loop to start multiple instances
for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Check proxy %%i" %PYTHON_PATH% "%PROJECT_DIR%\artisan\proxy_https_checker.py" --limit=10 --admin=true --uid="check_proxy_%%i" --fileLock="%PROJECT_DIR%\tmp\locks\proxy_https_checker_%%i.lock"
)

for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Filter ports %%i" %PYTHON_PATH% "%PROJECT_DIR%\artisan\filter_duplicate_ips.py" --limit=10 --admin=true --uid="filter_duplicate_%%i" --fileLock="%PROJECT_DIR%\tmp\locks\filter_duplicate_ips_%%i.lock"
)
