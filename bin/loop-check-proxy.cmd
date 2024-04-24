@echo off

for /l %%x in (1, 1, 3) do (
   echo "run proxy checking #%%x"
   echo.
   check-proxy > NUL
   echo.
)
