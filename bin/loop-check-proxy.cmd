@echo off

@REM REM loop-check-proxy 5

REM Set default value for max
set max=1

REM Check if numeric argument is provided and set max accordingly
if not "%~1"=="" (
    set max=%1
)

set CUSTOM_PHP_PATH=..\assets\php\php.exe

rem Check if custom PHP exists, if yes, set it as PHP_PATH
if exist "%CUSTOM_PHP_PATH%" (
    set PHP_PATH="%CUSTOM_PHP_PATH%"
) else (
    set PHP_PATH=php
)

REM Run the scripts for the specified number of times
for /l %%i in (1, 1, %max%) do (
    echo Running iteration %%i of %max%
    %PHP_PATH% proxyCheckerBackground.php
    echo.
    %PHP_PATH% artisan/proxyWorking.php
    timeout /t 30 /nobreak >nul
)

exit /b 0
