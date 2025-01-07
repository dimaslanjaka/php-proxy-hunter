#!/bin/bash

# Get the directory of the current script
SCRIPT_DIR=$(realpath "$(dirname "$0")")

# Set current working directory to parent directory of assets/systemctl/
CWD=$(realpath "$(dirname "$(dirname "$SCRIPT_DIR")")")

echo "Current directory: $CWD"

# Load .env file
if [ -f "$CWD/.env" ]; then
  source "$CWD/.env"
fi

# Detect Python virtual environment bin by operating system
if [ "$(uname -s)" = "Darwin" ] || [ "$(uname -s)" = "Linux" ]; then
  # Unix-based systems (Linux, macOS)
  VENV_BIN="$CWD/venv/bin"
else
  # Assume Windows
  VENV_BIN="$CWD/venv/Scripts"
fi

# Check if PATH is set
export PATH="${PATH:-/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin}:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN"

# Check if the virtual environment exists
if [ ! -x "$VENV_BIN/gunicorn" ]; then
  echo "Error: gunicorn not found in virtual environment at $VENV_BIN."
  exit 1
fi

# Prepare log files with the current date
DATE=$(date +'%Y-%m-%d')
LOG_DIR="$CWD/tmp/logs"
ACCESS_LOGFILE="$LOG_DIR/gunicorn-access-$DATE.log"
ERROR_LOGFILE="$LOG_DIR/gunicorn-error-$DATE.log"

# Ensure log directory exists
mkdir -p "$LOG_DIR"

# Start gunicorn and redirect logs
exec "$VENV_BIN/gunicorn" --workers 1 --bind unix:"$CWD/tmp/gunicorn.sock" django_backend.wsgi:application --access-logfile "$ACCESS_LOGFILE" --error-logfile "$ERROR_LOGFILE"
