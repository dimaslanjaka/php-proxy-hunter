#!/bin/bash

# Set current directory as working directory
CWD="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

# Set www-data user for subsequent commands
USER="www-data"

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

# Load .env file
if [ -f "$CWD/.env" ]; then
  source "$CWD/.env"
fi

r_cmd() {
  COMMAND_RUNNER=$1
  FILE_PATH=$2
  COMMAND_ARGS=${@:3}
  is_running=false

  # check script is running
  if [[ "$OSTYPE" == "cygwin" || "$OSTYPE" == "msys" ]]; then
    # Use ps for Windows (Cygwin)
    if ps aux | grep -v grep | grep "$COMMAND_RUNNER $CWD/$FILE_PATH" >/dev/null; then
      is_running=true
    fi
    wmic process where "name='$COMMAND_RUNNER.exe'" get commandline /format:list | grep -i "$FILE_PATH"
    if wmic process where "name='$COMMAND_RUNNER.exe'" get commandline /format:list | grep -i "$FILE_PATH" >/dev/null; then
      is_running=true
    fi
  else
    # Use pgrep for Unix-like systems
    if pgrep -f "$COMMAND_RUNNER $CWD/$FILE_PATH" >/dev/null; then
      is_running=true
    fi
  fi

  if $is_running; then
    echo "$CWD/$FILE_PATH is still running."
  else
    # Check if OS is Linux and nginx is installed
    if [[ "$(uname)" == "Linux" && -x "$(command -v nginx)" ]]; then
      USER="www-data"
      COMMAND="source $CWD/venv/bin/activate && $COMMAND_RUNNER $FILE_PATH $COMMAND_ARGS"
      echo "Executing as $USER: $COMMAND"
      sudo -u "$USER" -H bash -c "$COMMAND"
    else
      COMMAND="source $CWD/venv/Scripts/activate && $COMMAND_RUNNER $FILE_PATH $COMMAND_ARGS"
      echo "Executing: $COMMAND"
      eval "$COMMAND"
    fi
  fi
}

# Example usage:
# r_cmd "python" "manage.py" "runserver"
# r_cmd "php" "path/to/script.php" "arg1 arg2"

# su -s /bin/sh -c "php $CWD/send_curl.php --url=https://dev.webmanajemen.com:8000/proxy/filter" "$USER"
su -s /bin/sh -c "php $CWD/send_curl.php --url=https://dev.webmanajemen.com:8000/proxy/check" "$USER"
bash "$CWD/bin/check-proxy-parallel"
r_cmd "python" "filterPortsDuplicate.py" "--max=10"
r_cmd "python" "proxyCheckerReal.py" "--max=10"
