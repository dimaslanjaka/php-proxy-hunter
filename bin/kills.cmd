@echo off

@rem wmic process where "name like '%java%'" delete
@REM call wmic process where "name like '%%java%%'" delete
@REM call wmic process where "name like 'clangd.exe'" delete
@REM call wmic process where "name like 'aapt.exe'" delete
@REM call wmic process where "name like 'java.exe'" delete
@REM call wmic process where "name like 'ninja.exe'" delete
@REM call wmic process where "name like 'clang.exe'" delete
@REM call wmic process where "name like 'clang++.exe'" delete
@REM taskkill /f /im jqs.exe
@REM taskkill /f /im javaw.exe
@REM taskkill /f /im java.exe
@REM taskkill /f /im javac.exe
taskkill /f /im python.exe
taskkill /F /IM php.exe
taskkill /F /IM node.exe
call wmic process where "name like 'chrome.exe'" delete
call wmic process where "name like 'webdriver.exe'" delete
call wmic process where "name like 'chromedriver.exe'" delete
call wmic process where "name like 'php.exe'" delete
call wmic process where "name like 'python.exe'" delete
call wmic process where "name like 'node.exe'" delete

@REM call rm *.lock
@REM call php bin/composer.phar install
@REM call touch yarn.lock
@REM call yarn install
