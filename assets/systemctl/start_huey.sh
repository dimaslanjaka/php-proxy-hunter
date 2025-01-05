#!/bin/bash

# Get the directory of the current script
SCRIPT_DIR=$(realpath "$(dirname "$0")")

# Set current working directory to the parent directory of the grandparent
CWD=$(realpath "$(dirname "$(dirname "$SCRIPT_DIR")")")

echo "Current directory: $CWD"

# Load .env file if it exists
if [ -f "$CWD/.env" ]; then
  echo "Loading environment variables from $CWD/.env"
  # Use `set -a` and `set +a` to ensure exported variables are available
  set -a
  source "$CWD/.env"
  set +a
else
  echo "No .env file found in $CWD"
fi

# Detect Python virtual environment bin directory by operating system
case "$(uname -s)" in
  Darwin|Linux)
    # Unix-based systems (Linux, macOS)
    VENV_BIN="$CWD/venv/bin"
    ;;
  *)
    # Assume Windows
    VENV_BIN="$CWD/venv/Scripts"
    ;;
esac

# Update PATH environment variable
export PATH="${PATH:-/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin}:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN"

echo "PATH set to: $PATH"

# Ensure the Python virtual environment exists
if [ ! -x "$VENV_BIN/python" ]; then
  echo "Error: Python virtual environment not found at $VENV_BIN"
  exit 1
fi

# Execute the desired Python command
exec "$VENV_BIN/python" "$CWD/manage.py" run_huey --workers=1
