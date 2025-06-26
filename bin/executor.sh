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

echo "Current directory: $CWD"
echo "Command: $COMMAND"

# Check if OS is Linux and nginx is installed
if [[ "$(uname)" == "Linux" && -x "$(command -v nginx)" ]]; then
  USER="www-data"
  sudo -u "$USER" -H bash -c "source $CWD/venv/bin/activate && $COMMAND"
else
  eval "source $CWD/venv/Scripts/activate && $COMMAND"
fi

# Example usage: bash bin/executor.sh python3 filterPortsDuplicate.py --max=30
