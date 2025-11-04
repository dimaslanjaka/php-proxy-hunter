@echo off
REM Batch wrapper to run the PHP sample-error script on Windows
php "%~dp0sample-error.php"
exit /b %errorlevel%
