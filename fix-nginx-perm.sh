#!/bin/bash

# Check if user is root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Array of files to remove
lock_files=("proxyWorking.lock" "proxyChecker.lock")

# Loop through the array
for file in "${lock_files[@]}"; do
    # Check if the file exists
    if [ -e "$file" ]; then
        # Remove the file
        rm "$file"
        echo "Removed $file"
    else
        echo "$file does not exist"
    fi
done

# Set permissions
# shellcheck disable=SC2035
chmod 777 *.txt
# shellcheck disable=SC2035
chmod 755 *.html *.js
# shellcheck disable=SC2035
chmod 755 *.css
chmod 755 js/*.js
chmod 777 config
chmod 755 config/*
chmod 777 tmp .cache data
chmod 644 data/*.php
# shellcheck disable=SC2035
chmod 644 *.php
chmod 644 .env

# Create necessary directories and files
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

# Allow composer and indexing proxies to work
# shellcheck disable=SC2035
chown -R www-data:www-data *.php *.phar

if [ -d "xl" ]; then
    chown -R www-data:www-data xl
    chown -R www-data:www-data xl/*
    touch xl/index.html
fi

echo "Permission sets successful"

OUTPUT_FILE="proxyChecker.txt"
COMPOSER_LOCK="composer.lock"
COMPOSER_PHAR="composer.phar"

# Install or update composer packages
if [ ! -f "$COMPOSER_LOCK" ]; then
  su -s /bin/sh -c "php $COMPOSER_PHAR install --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" www-data
else
  su -s /bin/sh -c "php $COMPOSER_PHAR update --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" www-data
fi

# Validate proxies-all.php not running before indexing proxies
if pgrep -f "proxies-all.php" >/dev/null; then
    echo "Proxies indexing is still running."
else
    # If proxies-all.php is not running, execute the command
    su -s /bin/sh -c "php proxies-all.php --admin=true >> $OUTPUT_FILE 2>&1 &" www-data
fi

# Validate filterPortsDuplicate.php not running before indexing proxies
if pgrep -f "filterPortsDuplicate.php" >/dev/null; then
    echo "Filter ports duplicate is still running."
else
    # If filterPortsDuplicate.php is not running, execute the command
    su -s /bin/sh -c "php filterPortsDuplicate.php --admin=true >> $OUTPUT_FILE 2>&1 &" www-data
fi

# Set permissions for vendor directory
chmod 777 vendor
touch vendor/index.html

echo "Composer installed"

# Fix ownership
# shellcheck disable=SC2035
chown -R www-data:www-data *.php *.txt *.json *.js *.html src data tmp vendor assets
# shellcheck disable=SC2035
chown -R www-data:www-data .cache config *.css *.lock js .htaccess .env

echo "Ownership fixed"

# Enable Git LFS and track large files
git lfs install
# shellcheck disable=SC2035
git lfs track *.rar

echo "Large files tracked"

# Restart services
systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"
