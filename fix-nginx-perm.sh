#!/bin/bash

# Ensure script runs as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Set www-data user for subsequent commands
# Your PHP user on ubuntu
USER="www-data"

# Check if the current directory is a Git repository
if [ -d ".git" ] || git rev-parse --git-dir > /dev/null 2>&1; then
    echo "Current directory is a Git repository."
    git submodule update -i -r
else
    echo "Current directory is not a Git repository."
fi

# Array of files to remove
lock_files=("proxyWorking.lock" "proxyChecker.lock")

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
chmod 777 *.txt
chmod 755 *.html *.js
chmod 755 *.css
chmod 755 js/*.js
chmod 777 config
chmod 755 config/*
chmod 777 tmp .cache data
chmod 644 data/*.php
chmod 644 *.php
chmod 644 .env

# Create necessary directories and index.html files
mkdir -p tmp/cookies
touch tmp/cookies/index.html
touch tmp/index.html
mkdir -p config
touch config/index.html
mkdir .cache
touch .cache/index.html

# Additional permissions for specific directories
if [ -d "assets/proxies" ]; then
    chmod 777 assets/proxies
    chmod 755 assets/proxies/*
    touch assets/proxies/index.html
fi
if [ -d "packages" ]; then
    chown -R "$USER":"$USER" packages
    chown -R "$USER":"$USER" packages/*
fi

# Allow composer and indexing proxies to work
chown -R "$USER":"$USER" *.php *.phar

if [ -d "xl" ]; then
    chown -R "$USER":"$USER" xl
    chown -R "$USER":"$USER" xl/*
    touch xl/index.html
fi

echo "Permission sets successful"

OUTPUT_FILE="proxyChecker.txt"
COMPOSER_LOCK="composer.lock"
COMPOSER_PHAR="composer.phar"

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
    su -s /bin/sh -c "php proxies-all.php --admin=true >> $OUTPUT_FILE 2>&1 &" "$USER"
fi

# Validate filterPortsDuplicate.php not running before indexing proxies
if pgrep -f "filterPortsDuplicate.php" >/dev/null; then
    echo "Filter ports duplicate is still running."
else
    su -s /bin/sh -c "php filterPortsDuplicate.php --admin=true --endless=true >> $OUTPUT_FILE 2>&1 &" "$USER"
fi

# Set permissions for vendor directory
chmod 777 vendor
touch vendor/index.html

echo "Composer installed"

# Fix ownership for various directories and file types
chown -R "$USER":"$USER" *.php *.txt *.json *.js *.html src data tmp vendor assets
chown -R "$USER":"$USER" .cache config *.css *.lock js .htaccess .env

echo "Ownership fixed"

# Enable Git LFS and track large files
git lfs install
git lfs track *.rar

echo "Large files tracked"

# Restart services
systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"

# Install python requirements
sudo -u "$USER" -H bash -c "python3.11 -m venv /var/www/html/venv"
sudo -u "$USER" -H bash -c "source /var/www/html/venv/bin/activate && python /var/www/html/requirements_install.py"
