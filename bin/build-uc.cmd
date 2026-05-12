@echo off

echo Building userscripts...

call rollup -c rollup.userscript.js
call node src/userscripts/test.js
