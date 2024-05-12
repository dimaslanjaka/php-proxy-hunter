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
chmod 777 *.txt
chmod 755 *.html *.js
chmod 755 *.css
chmod 755 js/*.js
chmod 777 config
chmod 755 config/*
chmod 777 tmp .cache data
chmod 644 data/*
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
    chmod 755 "assets/proxies"
    touch "assets/proxies/index.html"
fi

# Allow composer and indexing proxies to work
chown -R www-data:www-data *.php *.phar

echo "Permission sets successful"

OUTPUT_FILE="/var/www/html/proxyChecker.txt"
COMPOSER_LOCK="/var/www/html/composer.lock"
COMPOSER_PHAR="/var/www/html/composer.phar"

# Install or update composer packages
if [ ! -f "$COMPOSER_LOCK" ]; then
  su -s /bin/sh -c "php $COMPOSER_PHAR install >> $OUTPUT_FILE 2>&1" www-data
else
  su -s /bin/sh -c "php $COMPOSER_PHAR update >> $OUTPUT_FILE 2>&1" www-data
fi

# Validate proxies-all.php not running before indexing proxies
if pgrep -f "proxies-all.php" >/dev/null; then
    echo "Proxies indexing is still running."
else
    # If proxies-all.php is not running, execute the command
    su -s /bin/sh -c "php /var/www/html/proxies-all.php >> $OUTPUT_FILE 2>&1 &" www-data
fi

# Set permissions for vendor directory
chmod 777 vendor
touch vendor/index.html

echo "Composer installed"

# Fix ownership
chown -R www-data:www-data *.php *.txt *.json *.js *.html src data tmp vendor assets
chown -R www-data:www-data .cache config *.css *.lock js .htaccess .env

echo "Ownership fixed"

# Enable Git LFS and track large files
git lfs install
git lfs track *.rar

echo "Large files tracked"

# Restart services
systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"
