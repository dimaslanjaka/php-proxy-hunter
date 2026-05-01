@echo off
REM Windows batch script to run deploy-vps.mjs using Node.js

set SCRIPT_DIR=%~dp0
set PROJECT_ROOT=%SCRIPT_DIR%..

@rem unstaged .husky/hash.txt and undo changes to it
git restore --staged .husky/hash.txt 2>nul || rem
git restore .husky/hash.txt 2>nul || rem

REM Use node to run the .mjs file with no warnings
node --no-warnings=ExperimentalWarning "%PROJECT_ROOT%\bin\deploy-vps.mjs" %*
