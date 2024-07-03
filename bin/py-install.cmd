@echo off

:: Activate the virtual environment
call venv\Scripts\activate

call python -m pip install -r requirements.txt

:: Deactivate the virtual environment
call venv\Scripts\deactivate

@REM D:\xampp\htdocs\venv\Scripts\python.exe -m pip install --upgrade pip