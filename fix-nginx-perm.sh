#!/bin/bash

# Ensure script runs as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Set www-data user for subsequent commands
USER="www-data"

# Get the absolute path of the current script directory
CWD="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

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

# Check if Git is installed
if command -v git >/dev/null 2>&1; then
    # Check if the current directory is a Git repository
    if [ -d "$CWD/.git" ] || git -C "$CWD" rev-parse --git-dir >/dev/null 2>&1; then
        echo "Current directory is a Git repository. Updating submodules..."
        bash "$CWD/bin/submodule-update"
    else
        echo "Current directory is not a Git repository."
    fi
else
    echo "Git is not installed. Please install Git to proceed."
fi

# Array of files to remove
lock_files=("$CWD/proxyWorking.lock" "$CWD/proxyChecker.lock")

# Loop through the array to remove lock files
for file in "${lock_files[@]}"; do
    if [ -e "$file" ]; then
        rm "$file"
        echo "Removed $file"
    else
        echo "$file does not exist"
    fi
done

OUTPUT_FILE="$CWD/proxyChecker.txt"
COMPOSER_LOCK="$CWD/composer.lock"
COMPOSER_PHAR="$CWD/composer.phar"

# Install or update composer packages
if [ ! -f "$COMPOSER_LOCK" ]; then
    su -s /bin/sh -c "php $COMPOSER_PHAR install --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" "$USER"
else
    su -s /bin/sh -c "php $COMPOSER_PHAR update --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" "$USER"
fi

# Function to check if a process is running and start it if not, or force run it
run_php_if_not_running() {
    local script_name="$1"
    local script_args="$2"
    local force="${3:-false}" # Default to false if not provided

    if pgrep -f "$script_name" >/dev/null; then
        if [ "$force" = "true" ]; then
            echo "$script_name is running but will be forcefully restarted."
            pkill -f "$script_name"
            su -s /bin/sh -c "php $CWD/$script_name $script_args >> $OUTPUT_FILE 2>&1 &" "$USER"
        else
            echo "$script_name is still running."
        fi
    else
        su -s /bin/sh -c "php $CWD/$script_name $script_args >> $OUTPUT_FILE 2>&1 &" "$USER"
    fi
}

# Validate and run scripts if not already running
run_php_if_not_running "proxies-all.php" "--admin=true"
run_php_if_not_running "filterPortsDuplicate.php" "--admin=true --delete=true"
run_php_if_not_running "filterPorts.php" "--admin=true"
run_php_if_not_running "proxyChecker.php" "--admin=true --max=1000" "true"

echo "Composer installed"

# Enable Git LFS and track large files
git -C "$CWD" lfs install
git -C "$CWD" lfs track "*.rar"

echo "Large files tracked"

# Function to copy file if both source and destination exist
copy_if_both_exist() {
    local source_file="$1"
    local dest_file="$2"

    if [ -f "$source_file" ] && [ -f "$dest_file" ]; then
        cp "$source_file" "$dest_file"
        echo "Copied $source_file to $dest_file"
    else
        echo "Skipping. Source file $source_file or destination file $dest_file does not exist."
    fi
}

# Copy .htaccess_nginx.conf to /etc/nginx/sites-available/default
# copy_if_both_exist "$CWD/.htaccess_nginx.conf" "/etc/nginx/sites-available/default"

# Fix permissions
. "$CWD/bin/fix-perm"

# Restart services
. "$CWD/bin/restart-server"
