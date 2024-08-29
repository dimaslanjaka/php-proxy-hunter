@echo off

:: Generate a random string using PowerShell (16-character hex string)
for /f %%a in ('powershell -command "[Guid]::NewGuid().ToString('N').Substring(0,16)"') do set RANDOM_STRING=%%a

:: Alternatively, generate random bytes and convert to a hex string
:: certutil -generateSRandom 8 > nul
:: certutil -generateRandom -hex 8 | findstr /v /c:"CERT" > temp.txt
:: set /p RANDOM_STRING=<temp.txt
:: del temp.txt

:: Push to remote with the random branch name
git push -f private HEAD:update_%RANDOM_STRING%
