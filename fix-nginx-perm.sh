#!/bin/bash

# Ensure script runs as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Set www-data user for subsequent commands
USER="www-data"

# Get the absolute path of the current script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

# Add custom paths to PATH
export PATH="${SCRIPT_DIR}/node_modules/.bin:${SCRIPT_DIR}/bin:${SCRIPT_DIR}/vendor/bin:$PATH"

# Check if the current directory is a Git repository
if [ -d "$SCRIPT_DIR/.git" ] || git -C "$SCRIPT_DIR" rev-parse --git-dir > /dev/null 2>&1; then
    echo "Current directory is a Git repository."
    git -C "$SCRIPT_DIR" submodule update -i -r
else
    echo "Current directory is not a Git repository."
fi

# Array of files to remove
lock_files=("$SCRIPT_DIR/proxyWorking.lock" "$SCRIPT_DIR/proxyChecker.lock")

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
chmod 777 "$SCRIPT_DIR"/*.txt
chmod 755 "$SCRIPT_DIR"/*.html "$SCRIPT_DIR"/*.js
chmod 755 "$SCRIPT_DIR"/*.css
chmod 755 "$SCRIPT_DIR"/js/*.js
chmod 777 "$SCRIPT_DIR"/config
chmod 755 "$SCRIPT_DIR"/config/*
chmod 777 "$SCRIPT_DIR"/tmp "$SCRIPT_DIR"/.cache "$SCRIPT_DIR"/data
chmod 644 "$SCRIPT_DIR"/data/*.php
chmod 644 "$SCRIPT_DIR"/*.php
chmod 644 "$SCRIPT_DIR"/.env

# Create necessary directories and index.html files
mkdir -p "$SCRIPT_DIR/tmp/cookies"
touch "$SCRIPT_DIR/tmp/cookies/index.html"
touch "$SCRIPT_DIR/tmp/index.html"
mkdir -p "$SCRIPT_DIR/config"
touch "$SCRIPT_DIR/config/index.html"
mkdir -p "$SCRIPT_DIR/.cache"
touch "$SCRIPT_DIR/.cache/index.html"

# Additional permissions for specific directories if they exist
if [ -d "$SCRIPT_DIR/assets/proxies" ]; then
    chmod 777 "$SCRIPT_DIR/assets/proxies"
    chmod 755 "$SCRIPT_DIR/assets/proxies"/*
    touch "$SCRIPT_DIR/assets/proxies/index.html"
fi
if [ -d "$SCRIPT_DIR/packages" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/packages"
    chown -R "$USER":"$USER" "$SCRIPT_DIR/packages"/*
fi
if [ -d "$SCRIPT_DIR/xl" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/xl"
    chown -R "$USER":"$USER" "$SCRIPT_DIR/xl"/*
    touch "$SCRIPT_DIR/xl/index.html"
fi

# Allow composer and indexing proxies to work
chown -R "$USER":"$USER" "$SCRIPT_DIR"/*.php "$SCRIPT_DIR"/*.phar

chown -R "$USER":"$USER" "$SCRIPT_DIR/bin"
chmod 755 "$SCRIPT_DIR/bin"/*

echo "Permission sets successful"

OUTPUT_FILE="$SCRIPT_DIR/proxyChecker.txt"
COMPOSER_LOCK="$SCRIPT_DIR/composer.lock"
COMPOSER_PHAR="$SCRIPT_DIR/composer.phar"

# Install or update composer packages
if [ ! -f "$COMPOSER_LOCK" ]; then
  su -s /bin/sh -c "php $COMPOSER_PHAR install --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" "$USER"
else
  su -s /bin/sh -c "php $COMPOSER_PHAR update --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" "$USER"
fi

# Validate proxies-all.php not running before indexing proxies
if pgrep -f "proxies-all.php" >/dev/null; then
    echo "Proxies indexing is still running."
else
    su -s /bin/sh -c "php $SCRIPT_DIR/proxies-all.php --admin=true >> $OUTPUT_FILE 2>&1 &" "$USER"
fi

# Validate filterPortsDuplicate.php not running before indexing proxies
if pgrep -f "filterPortsDuplicate.php" >/dev/null; then
    echo "Filter ports duplicate is still running."
else
    su -s /bin/sh -c "php $SCRIPT_DIR/filterPortsDuplicate.php --admin=true --delete=true >> $OUTPUT_FILE 2>&1 &" "$USER"
fi

# Set permissions for vendor directory
chmod 777 "$SCRIPT_DIR/vendor"
touch "$SCRIPT_DIR/vendor/index.html"

echo "Composer installed"

# Fix ownership for various directories and file types
chown -R "$USER":"$USER" "$SCRIPT_DIR"/*.php "$SCRIPT_DIR"/*.txt "$SCRIPT_DIR"/*.json "$SCRIPT_DIR"/*.js "$SCRIPT_DIR"/*.html "$SCRIPT_DIR"/src "$SCRIPT_DIR"/data "$SCRIPT_DIR"/tmp "$SCRIPT_DIR"/vendor "$SCRIPT_DIR"/assets
chown -R "$USER":"$USER" "$SCRIPT_DIR"/.cache "$SCRIPT_DIR"/config "$SCRIPT_DIR"/*.css "$SCRIPT_DIR"/*.lock "$SCRIPT_DIR"/js "$SCRIPT_DIR"/.htaccess "$SCRIPT_DIR"/.env

echo "Ownership fixed"

# Enable Git LFS and track large files
git -C "$SCRIPT_DIR" lfs install
git -C "$SCRIPT_DIR" lfs track "*.rar"

echo "Large files tracked"

# Restart services
systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"

# Install python requirements
sudo -u "$USER" -H bash -c "python3.11 -m venv $SCRIPT_DIR/venv"
sudo -u "$USER" -H bash -c "source $SCRIPT_DIR/venv/bin/activate && python $SCRIPT_DIR/requirements_install.py"

chown -R "$USER":"$USER" "$SCRIPT_DIR/venv"
chmod 755 "$SCRIPT_DIR/venv/bin"/*
