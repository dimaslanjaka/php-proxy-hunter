@echo off

@REM REM loop-check-proxy --max 5

REM Set default value for max
set max=1

REM Check if --max argument is provided and set max accordingly
if not "%~1"=="" (
    set max=%~1
    echo Max set to %max%
) else (
    echo Max not provided, using default value of %max%
)

REM Run the scripts for the specified number of times
for /l %%i in (1, 1, %max%) do (
    echo Running iteration %%i of %max%
    php proxyCheckerBackground.php
    echo.
    php proxyWorking.php
)

exit /b 0