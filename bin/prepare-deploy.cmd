@echo off
setlocal

@REM run 'bin/prepare-deploy.cmd' to prepare for deployment by pushing master and merging into python branch
@REM then run 'bin/deploy.cmd' to deploy to VPS

REM Get current branch name
for /f "delims=" %%i in ('git rev-parse --abbrev-ref HEAD') do set CURRENT_BRANCH=%%i

REM Only run if current branch is master
if /I "%CURRENT_BRANCH%"=="master" (

    REM Unstage .husky/hash.txt
    call git restore --staged .husky/hash.txt

    REM Reset working tree changes for .husky/hash.txt
    call git checkout -- .husky/hash.txt

    REM Push current master branch
    call git push

    REM Switch to python branch
    call git checkout python

    REM Pull master into python without committing
    call git pull origin master --no-commit

) else (
    echo Skipped: current branch is "%CURRENT_BRANCH%", not "master".
)

endlocal
