@echo off

set CUSTOM_PHP_PATH=..\assets\php\php.exe

rem Check if custom PHP exists, if yes, set it as PHP_PATH
if exist "%CUSTOM_PHP_PATH%" (
    set PHP_PATH="%CUSTOM_PHP_PATH%"
) else (
    set PHP_PATH=php
)

%PHP_PATH% proxyCheckerBackground.php
echo.
%PHP_PATH% artisan/proxyWorking.php
