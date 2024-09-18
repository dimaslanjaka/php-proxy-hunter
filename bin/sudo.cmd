@echo off
setlocal

:: Preserve the current directory
set "currentDir=%cd%"

:: Create the administrative VBScript if it doesn't exist
if exist "%temp%\getadmin.vbs" del "%temp%\getadmin.vbs"

:: Check if the script is running with administrative privileges
fsutil dirty query %systemdrive% 1>nul 2>nul || (
    echo Set UAC = CreateObject^("Shell.Application"^) : UAC.ShellExecute "cmd.exe", "/k cd /d ""%currentDir%"" && ""%~s0"" %*", "", "runas", 1 >> "%temp%\getadmin.vbs"
    "%temp%\getadmin.vbs"
    exit /B
)

:: Proceed with the script
:: Your main code here

endlocal
