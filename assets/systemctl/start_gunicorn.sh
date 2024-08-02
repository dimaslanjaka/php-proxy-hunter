#!/bin/bash

CWD="/var/www/html"

# Load .env file
if [ -f "$CWD/.env" ]; then
    source "$CWD/.env"
fi

# Detect python virtual bin by operating system
if [ "$(uname -s)" = "Darwin" ] || [ "$(uname -s)" = "Linux" ]; then
    # Unix-based systems (Linux, macOS)
    VENV_BIN="$CWD/venv/bin"
else
    # Assume Windows
    VENV_BIN="$CWD/venv/Scripts"
fi

# Check if PATH is set
if [ -z "$PATH" ]; then
    export PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN
else
    export PATH=$PATH:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN
fi

DATE=$(date +'%Y-%m-%d')
LOG_DIR=/var/www/html/tmp/logs
ACCESS_LOGFILE=$LOG_DIR/gunicorn-access-$DATE.log
ERROR_LOGFILE=$LOG_DIR/gunicorn-error-$DATE.log

mkdir -p $LOG_DIR

exec /var/www/html/venv/bin/gunicorn --workers 3 --bind unix:/var/www/html/tmp/gunicorn.sock django_backend.wsgi:application --access-logfile $ACCESS_LOGFILE --error-logfile $ERROR_LOGFILE
