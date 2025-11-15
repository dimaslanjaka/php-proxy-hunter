@echo off
setlocal
REM Forward to project bin\py.cmd so `py` in project root uses the project venv.
call "%~dp0bin\py.cmd" %*
endlocal
