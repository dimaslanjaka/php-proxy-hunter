@echo off

:: Activate the virtual environment
call venv\Scripts\activate

call python requirements_install.py

:: Deactivate the virtual environment
call venv\Scripts\deactivate

@REM D:\xampp\htdocs\venv\Scripts\python.exe -m pip install --upgrade pip