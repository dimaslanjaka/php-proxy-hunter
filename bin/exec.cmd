@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
set "PY_WRAPPER=%SCRIPT_DIR%py.cmd"
set "RUN_PY=%SCRIPT_DIR%exec.py"

if not exist "%PY_WRAPPER%" (
  echo [ERROR] Python wrapper not found: %PY_WRAPPER%
  exit /b 1
)

if not exist "%RUN_PY%" (
  echo [ERROR] Runner script not found: %RUN_PY%
  exit /b 1
)

call "%PY_WRAPPER%" "%RUN_PY%" %*
exit /b %ERRORLEVEL%
