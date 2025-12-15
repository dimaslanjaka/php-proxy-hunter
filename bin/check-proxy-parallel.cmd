@echo off

REM Number of instances to run in parallel
set NUM_INSTANCES=5

FOR %%i in ("%~dp0.") do SET "parent_folder=%%~fi"

SET "cwd=%parent_folder%\..\"

echo "CWD %cwd%"

REM Loop to start multiple instances
for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Check proxy %%i" php "%cwd%proxyCheckerParallel.php" --max=400 --admin=true
)

for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Filter ports %%i" php "%cwd%artisan/filterPortsDuplicate.php" --max=400 --admin=true --delete=true
)
