@echo off

:: A script to run Django management commands easily

set CWD=%~dp0
set CWD=%CWD:~0,-1%
set PROJECT_DIR=%CWD%\..

call %PROJECT_DIR%\venv\Scripts\activate.bat
python %PROJECT_DIR%\manage.py %*
