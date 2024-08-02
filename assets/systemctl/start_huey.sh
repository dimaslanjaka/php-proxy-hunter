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

# Define log file locations
# DATE=$(date +'%Y-%m-%d')
# LOG_DIR="/var/www/html/logs"
# LOG_FILE="$LOG_DIR/huey.log"
# ERROR_LOG_FILE="$LOG_DIR/huey-error.log"

# Ensure the log directory exists and is writable
# mkdir -p $LOG_DIR
# chown www-data:www-data $LOG_DIR

# Start the Huey worker with built-in logging and redirect output to the specified log file
exec /var/www/html/venv/bin/python /var/www/html/manage.py run_huey
