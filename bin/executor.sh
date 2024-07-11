#!/bin/bash

# Check if arguments are provided
if [ $# -eq 0 ]; then
  echo "Usage: bash bin/executor.sh <command>"
  exit 1
fi

# Combine all arguments into a single command
COMMAND="$@"

# Set current working directory to parent directory of bin/
CWD=$(dirname "$(dirname "$(realpath "$0")")")

# Calculate MD5 hash of the command string
HASH=$(echo -n "$COMMAND" | md5sum | cut -d ' ' -f 1)

# Define output file path
OUTPUT_FILE="$CWD/tmp/logs/$HASH.log"

mkdir -p "$CWD/tmp/logs"

# Check if OS is Linux and nginx is installed
if [[ "$(uname)" == "Linux" && -x "$(command -v nginx)" ]]; then
  USER="www-data"
  VENV_ACTIVATE="$CWD/venv/bin/activate"
  # Activate the virtual environment
  source "$VENV_ACTIVATE" # or . "$VENV_ACTIVATE"
  su -s /bin/sh -c "$COMMAND" "$USER"
else
  VENV_ACTIVATE="$CWD/venv/Scripts/activate"
  # Activate the virtual environment
  source "$VENV_ACTIVATE" # or . "$VENV_ACTIVATE"
  eval "$COMMAND"
fi

# bash bin/executor.sh python proxyChecker.py
