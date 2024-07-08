@echo off

REM Number of instances to run in parallel
set NUM_INSTANCES=5

FOR %%i in ("%~dp0.") do SET "parent_folder=%%~fi"

SET "cwd=%parent_folder%\..\"

echo "CWD %cwd%"

REM Loop to start multiple instances
for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Check proxy %%i" php "%cwd%proxyCheckerParallel.php --admin=true" --max=400
)

for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Filter ports %%i" php "%cwd%filterPortsDuplicate.php --admin=true"
)