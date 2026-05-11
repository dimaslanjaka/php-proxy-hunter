@echo off

set CUSTOM_PHP_PATH=..\assets\php\php.exe

rem Check if custom PHP exists, if yes, set it as PHP_PATH
if exist "%CUSTOM_PHP_PATH%" (
    set PHP_PATH="%CUSTOM_PHP_PATH%"
) else (
    set PHP_PATH=php
)

set CWD=%~dp0
set CWD=%CWD:~0,-1%
set PROJECT_DIR=%CWD%\..
set PYTHON_PATH="%PROJECT_DIR%\bin\py.cmd"

%PYTHON_PATH% %PROJECT_DIR%\artisan\proxy_https_checker.py %*

