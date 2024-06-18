@echo off
setlocal enabledelayedexpansion

@REM remove all github actions cache, leaving last one

REM Load the .env file if it exists
if exist .env (
    for /f "tokens=*" %%i in ('type .env ^| findstr /r /v "^#"') do (
        set %%i
    )
    echo .env file loaded
)

set REPO="dimaslanjaka/php-proxy-hunter"

REM Execute curl command and store the JSON response
curl -s -X GET ^
  -H "Accept: application/vnd.github.v3+json" ^
  -H "Authorization: token %ACCESS_TOKEN%" ^
  "https://api.github.com/repos/%REPO%/actions/caches" > tmp\response.json

REM Parse the JSON response and extract ids
set "skip_first_id="
for /f "tokens=1,2 delims=:" %%a in ('type tmp\response.json ^| findstr /C:"\"id\":"') do (
  if defined skip_first_id (
    set id=%%b
    set id=!id:~1,-1!
    echo Deleting cache ID !id!
    curl -s -X DELETE ^
      -H "Accept: application/vnd.github.v3+json" ^
      -H "Authorization: token %ACCESS_TOKEN%" ^
      "https://api.github.com/repos/%REPO%/actions/caches/!id!"
  )
  set "skip_first_id=true"
)

REM Clean up - delete tmp\response.json
del tmp\response.json
