@echo off
REM Install Python development requirements
pip install -r requirements-dev.txt
if %errorlevel% neq 0 (
    echo Failed to install development requirements.
    exit /b %errorlevel%
)
echo Development requirements installed successfully.
