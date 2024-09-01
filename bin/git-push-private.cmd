@echo off

:: Call PowerShell to get the current date and time in Asia/Jakarta timezone
for /f "tokens=* usebackq" %%i in (`powershell -Command "(Get-Date).ToUniversalTime().AddHours(7).ToString('yyyy-MM-dd_HH-mm')"`) do set datetime=%%i

:: Display the result
echo The current datetime in Asia/Jakarta timezone is: %datetime%

:: Push to remote with the random branch name
git push -f private HEAD:update_%datetime%
