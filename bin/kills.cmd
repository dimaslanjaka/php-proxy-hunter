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

call wmic process where "name like 'chrome.exe'" delete
call wmic process where "name like 'webdriver.exe'" delete