@echo off
REM Set workspace folder to the directory where this script is located
set "WORKSPACE_FOLDER=%~dp0"
REM Remove trailing backslash if present
if "%WORKSPACE_FOLDER:~-1%"=="\" set "WORKSPACE_FOLDER=%WORKSPACE_FOLDER:~0,-1%"

REM Build the custom PATH
set "CUSTOM_PATH=%LOCALAPPDATA%\nvm;C:\nvm4w\nodejs;C:\Program Files\Nox\bin;D:\Program Files\Nox\bin;C:\Program Files\Git\cmd;C:\Program Files\Git\usr\bin;%PATH%;%WORKSPACE_FOLDER%\node_modules\.bin;%WORKSPACE_FOLDER%\bin;%WORKSPACE_FOLDER%\vendor\bin;C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;C:\laragon\bin\php\php-8.4.11-Win32-vs17-x64;C:\laragon\bin\git\bin;C:\laragon\bin\python\python-3.13;C:\laragon\bin\memcached\memcached-1.6.8-win64-mingw"

REM Open a new cmd window with the custom PATH and current directory
start "" cmd.exe /K "set PATH=%CUSTOM_PATH% && cd /d %CD%"
