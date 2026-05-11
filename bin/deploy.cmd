@echo off
setlocal

@REM run 'bin/prepare-deploy.cmd' to prepare for deployment by pushing master and merging into python branch
@REM then run 'bin/deploy.cmd' to deploy to VPS

for /f %%i in ('git rev-parse --git-dir 2^>nul') do set "GIT_DIR=%%i"

if not defined GIT_DIR (
    echo Not inside a git repository.
    exit /b 1
)

for /f %%i in ('git branch --show-current') do set "CURRENT_BRANCH=%%i"

echo Current branch: %CURRENT_BRANCH%

if /I not "%CURRENT_BRANCH%"=="python" (
    echo Skipped: not on python branch
    endlocal
    exit /b 0
)

set WAITING=
set BUSY=

:check_git_state

set BUSY=

if exist "%GIT_DIR%\MERGE_HEAD" set BUSY=1
if exist "%GIT_DIR%\rebase-merge" set BUSY=1
if exist "%GIT_DIR%\rebase-apply" set BUSY=1

git diff --name-only --diff-filter=U | findstr . >nul
if %errorlevel%==0 set BUSY=1

if defined BUSY (
    if not defined WAITING (
        <nul set /p ="Waiting for merge/rebase/conflicts to finish..."
        set WAITING=1
    )

    ping 127.0.0.1 -n 6 >nul
    goto check_git_state
)

if defined WAITING echo Done.

git push
if errorlevel 1 (
    endlocal
    exit /b 1
)

echo Waiting 3 seconds before deploy...
ping 127.0.0.1 -n 4 >nul

deploy-vps --backend

endlocal
exit /b 0
