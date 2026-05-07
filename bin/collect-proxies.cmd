@echo off
REM Wrapper to run artisan\proxyCollector.py from project root, forwarding all args
setlocal
set "SCRIPT_DIR=%~dp0.."
for %%I in ("%SCRIPT_DIR%") do set "CWD=%%~fI"

REM Always use project-local bin\py.cmd
"%CWD%\bin\py.cmd" "%CWD%\artisan\proxyCollector.py" %*
endlocal
