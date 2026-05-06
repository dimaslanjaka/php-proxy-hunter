
@echo off

setlocal

set CWD=%~dp0
set CWD=%CWD:~0,-1%
set PROJECT_DIR=%CWD%\..

call "%PROJECT_DIR%\bin\py.cmd" "%PROJECT_DIR%\src\utils\process\process_usage.py" %*

endlocal
