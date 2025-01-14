@echo off
:: Check if the script is already running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting administrative privileges...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

:: Set working directory to the parent folder of the script
cd /d "%~dp0.."

:: Open Command Prompt in the parent folder
start cmd.exe
