#!/bin/bash

set -e # Exit immediately if a command exits with a non-zero status
set -u # Treat unset variables as an error

clear

# Detect OS
OS=$(uname -s)

# Ensure script runs as root on Linux
if [ "$OS" == "Linux" ] && [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Get the directory of the script
CWD="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "Script directory: $CWD"

# Load .env file
if [ -f "$CWD/.env" ]; then
    source "$CWD/.env"
fi

# Set www-data user for subsequent commands
USER="www-data"

# Check if /var/www/html exists on Linux
if [ "$OS" == "Linux" ] && [ -d "/var/www/html" ]; then
    sudo chown -R $USER:$USER /var/www/html
    echo "Ownership updated for /var/www/html"
fi

# Check if /var/www/.cache/pip exists on Linux
if [ "$OS" == "Linux" ] && [ -d "/var/www/.cache/pip" ]; then
    # Change ownership of the directory to the current user
    sudo chown -R $USER:$USER /var/www/.cache/pip
    echo "Ownership updated for /var/www/.cache/pip"
fi

# Define the virtual environment directory
VENV_DIR="$CWD/venv"

# Check if the virtual environment directory exists
if [ ! -d "$VENV_DIR" ]; then
    echo "Creating virtual environment at '$VENV_DIR'..."
    # Check if python3 exists
    if command -v python3 &>/dev/null; then
        PYTHON_BIN="python3"
    # If python3 does not exist, check if python exists
    elif command -v python &>/dev/null; then
        PYTHON_BIN="python"
    # If neither exists, exit the script with an error message
    else
        echo "Error: Neither python3 nor python is installed."
        exit 1
    fi
    if [ "$(uname -s)" = "Darwin" ] || [ "$(uname -s)" = "Linux" ]; then
        # Unix-based systems (Linux, macOS)
        sudo -u "$USER" -H "$PYTHON_BIN" -m venv "$VENV_DIR"
    else
        # Assume Windows
        $PYTHON_BIN -m venv "$VENV_DIR"
    fi
    echo "Virtual environment created successfully."
else
    echo "The virtual environment directory '$VENV_DIR' already exists. Skipping creation."
fi

# Determine the correct virtual environment bin path based on OS
case "$(uname -s)" in
Darwin | Linux)
    VENV_BIN="$CWD/venv/bin" # Unix-based systems (Linux, macOS)
    ;;
*)
    VENV_BIN="$CWD/venv/Scripts" # Assume Windows
    ;;
esac

# Activate the virtual environment
VENV_ACTIVATOR="$VENV_BIN/activate"
source "$VENV_ACTIVATOR"

# Create a temporary directory for pip cache
mkdir -p "$CWD/tmp/.cache/pip"
chmod -R 777 "$CWD/tmp/.cache/pip"

# Check for Python binary in the virtual environment
if [ -x "$VENV_DIR/bin/python" ]; then
    PYTHON_BINARY="$VENV_DIR/bin/python"
elif [ -x "$VENV_DIR/Scripts/python.exe" ]; then
    PYTHON_BINARY="$VENV_DIR/Scripts/python.exe"
else
    echo "Python binary not found in the virtual environment."
    exit 1
fi

# Check if PATH is set
if [ -z "$PATH" ]; then
    export PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN
else
    export PATH=$PATH:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN
fi

# Output the Python binary location
echo "Python binary location: $PYTHON_BINARY"

# Upgrade additional tools (pip, setuptools, wheel)
echo "Upgrading pip, setuptools, and wheel..."

if [[ "$OS" == "Linux" ]]; then
    # On Linux, use sudo to upgrade pip, setuptools, and wheel for www-data user
    sudo -u "$USER" -H "$PYTHON_BINARY" -m ensurepip
    sudo -u "$USER" -H "$PYTHON_BINARY" -m pip install --upgrade pip setuptools wheel --cache-dir "$CWD/tmp/.cache/pip"
else
    # On Windows, call Python directly
    "$PYTHON_BINARY" -m ensurepip --upgrade
    "$PYTHON_BINARY" -m pip install --upgrade pip setuptools wheel --cache-dir "$CWD/tmp/.cache/pip"
fi

# Install the required packages
echo "Installing required packages..."

REQUIREMENTS_SCRIPT="$CWD/requirements_install.py"

if [[ "$OS" == "Linux" ]]; then
    # On Linux, use sudo to run the package installation for www-data user
    sudo -u "$USER" -H bash -c "source $VENV_ACTIVATOR && $PYTHON_BINARY $REQUIREMENTS_SCRIPT --generate"
    sudo -u "$USER" -H "$PYTHON_BINARY" -m pip install -r "$CWD/requirements.txt" --cache-dir "$CWD/tmp/.cache/pip"
else
    # On Windows, call Python directly
    "$PYTHON_BINARY" "$REQUIREMENTS_SCRIPT" --generate
    "$PYTHON_BINARY" -m pip install -r "$CWD/requirements.txt" --cache-dir "$CWD/tmp/.cache/pip"
fi

echo "Requirements installed successfully."

# Install PHP Composer dependencies
COMPOSER_LOCK="$CWD/composer.lock"
COMPOSER_PHAR="$CWD/composer.phar"

if [[ "$OS" == "Linux" ]]; then
    # On Linux, use sudo to run the package installation for www-data user
    sudo -u "$USER" -H bash -c "php $COMPOSER_PHAR install --no-dev --no-interaction"
else
    # On Windows, execute the command using php
    php "$COMPOSER_PHAR" install --no-dev --no-interaction
fi

# Install Node.js dependencies

if [ -d "$HOME/.nvm" ]; then
  export NVM_DIR="$HOME/.nvm"
elif [ -d "/usr/local/nvm" ]; then
  export NVM_DIR="/usr/local/nvm"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" || "$OSTYPE" == "mingw"* ]]; then
  # Just continue without setting NVM_DIR for Windows systems
  echo "Windows environment detected. NVM_DIR will not be set."
else
  echo "Neither $HOME/.nvm nor /usr/local/nvm exists."
  exit 1
fi

# Check if the OS is Linux
if [ "$OS" == "Linux" ]; then
    # Check if NVM is installed and load it
    if [ -d "$NVM_DIR" ] && [ -s "$NVM_DIR/nvm.sh" ]; then
        # Load NVM into the shell session
        . "$NVM_DIR/nvm.sh"

        # Enable corepack for yarn
        corepack enable yarn
        # Install Node.js dependencies using yarn
        yarn install
    else
        echo "NVM is not installed. Please install NVM first."
    fi
else
    # Non-Linux system (e.g., Windows)
    # Enable corepack for yarn
    corepack enable yarn
    # Install Node.js dependencies using yarn
    yarn install
fi

# Apply cron jobs
if [ "$OS" == "Linux" ]; then
    sudo crontab -u $USER .crontab.txt
fi

# Build NodeJS environment
rollup -c

# Fix permissions
if [ "$OS" == "Linux" ]; then
    bash -e "$CWD/bin/fix-perm"
fi
