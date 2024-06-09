@echo off

REM Number of instances to run in parallel
set NUM_INSTANCES=5

REM Loop to start multiple instances
for /l %%i in (1, 1, %NUM_INSTANCES%) do (
    start "Instance %%i" php proxyCheckerParallel.php --max=400
)
