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

# Set permissions on files and directories
chmod 777 "$CWD"/*.txt
chmod 755 "$CWD"/*.html "$CWD"/*.js
chmod 755 "$CWD"/*.css
chmod 755 "$CWD"/js/*.js "$CWD"/userscripts/*.js
chmod 777 "$CWD"/config
chmod 755 "$CWD"/config/*
chmod 777 "$CWD"/tmp "$CWD"/.cache "$CWD"/data
chmod 644 "$CWD"/data/*.php
chmod 644 "$CWD"/*.php
chmod 644 "$CWD"/.env
chmod 755 "$CWD"/*.sh

# Create necessary directories and index.html files
mkdir -p "$CWD/tmp/cookies"
touch "$CWD/tmp/cookies/index.html"
touch "$CWD/tmp/index.html"
mkdir -p "$CWD/config"
touch "$CWD/config/index.html"
mkdir -p "$CWD/.cache"
touch "$CWD/.cache/index.html"

# Additional permissions for specific directories if they exist
if [ -d "$CWD/assets/proxies" ]; then
    chmod 777 "$CWD/assets/proxies"
    chmod 755 "$CWD/assets/proxies"/*
    touch "$CWD/assets/proxies/index.html"
fi
if [ -d "$CWD/packages" ]; then
    chown -R "$USER":"$USER" "$CWD/packages"
    chown -R "$USER":"$USER" "$CWD/packages"/*
fi
if [ -d "$CWD/public" ]; then
    chown -R "$USER":"$USER" "$CWD/public"
    chown -R "$USER":"$USER" "$CWD/public"/*
fi

# Allow composer and indexing proxies to work
chown -R "$USER":"$USER" "$CWD"/*.php "$CWD"/*.phar

chown -R "$USER":"$USER" "$CWD/bin"
chmod 755 "$CWD/bin"/*

echo "Permission sets successful"

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

# Set permissions for vendor directory
chmod 777 "$CWD/vendor"
touch "$CWD/vendor/index.html"

echo "Composer installed"

# Fix ownership for various directories and file types
chown -R "$USER":"$USER" "$CWD"/*.php "$CWD"/*.txt "$CWD"/*.json "$CWD"/*.js "$CWD"/*.html "$CWD"/src "$CWD"/data "$CWD"/tmp "$CWD"/vendor "$CWD"/assets
chown -R "$USER":"$USER" "$CWD"/.cache "$CWD"/config "$CWD"/*.css "$CWD"/*.lock "$CWD"/js "$CWD"/.htaccess "$CWD"/.env

echo "Ownership fixed"

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

# Restart services
touch "$CWD/assets/index.html"
touch "$CWD/assets/systemctl/index.html"

# Reload daemon
sudo systemctl daemon-reload

# Check and restart PHP-FPM if installed
# Array of PHP versions to check
php_versions=("7.2" "7.4" "8.0")

# Iterate over each PHP version
for version in "${php_versions[@]}"; do
    # Check if PHP FPM service is active
    if systemctl is-active --quiet php${version}-fpm; then
        sudo systemctl restart php${version}-fpm
        echo "Restarted PHP ${version} FPM"
    fi
done

# Check and restart Nginx if installed
if systemctl is-active --quiet nginx; then
    sudo nginx -t
    sudo systemctl restart nginx
    echo "Restarted Nginx"
fi

# Check and restart Spring Boot if installed
# if systemctl is-active --quiet spring; then
#     sudo systemctl restart spring
#     echo "Restarted Spring Boot"
# fi
