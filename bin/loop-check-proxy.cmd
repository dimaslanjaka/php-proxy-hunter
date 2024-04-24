@echo off

for /l %%x in (1, 1, 100) do (
   echo "run proxy checking #%%x"
   echo:
   php proxyCheckerBackground.php
)