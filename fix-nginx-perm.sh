#!/bin/bash
# shellcheck disable=SC2035

rm *.lock

mkdir -p tmp/cookies
touch tmp/cookies/index.html
touch tmp/index.html
mkdir -p config
touch config/index.html

sudo chmod 777 *.txt
sudo chmod 755 *.html
sudo chmod 755 *.js
sudo chmod 755 *.css
sudo chmod 755 js/*.js
sudo chmod 777 config
sudo chmod 755 config/*
sudo chmod 777 tmp
sudo chmod 777 .cache
sudo chmod 777 data
sudo chmod 644 data/*
sudo chmod 644 *.php
sudo chmod 644 .env

if [ -d "assets/proxies" ]; then
    sudo chmod 777 "assets/proxies"
    sudo chmod 777 "assets/proxies/*.txt"
    touch "assets/proxies/index.html"
fi

echo "permission sets successful"

COMPOSER_LOCK="/var/www/html/composer.lock"
COMPOSER_PHAR="/var/www/html/composer.phar"
OUTPUT_FILE="/var/www/html/proxyChecker.txt"

if [ ! -f "$COMPOSER_LOCK" ]; then
    su -s /bin/bash -c "php $COMPOSER_PHAR install >> $OUTPUT_FILE 2>&1" www-data
else
    su -s /bin/bash -c "php $COMPOSER_PHAR update >> $OUTPUT_FILE 2>&1" www-data
fi

su -s /bin/bash -c "php /var/www/html/proxies-all.php >> $OUTPUT_FILE 2>&1 &" www-data

chmod 777 vendor
touch vendor/index.html

echo "composer installed"

sudo chown -R www-data:www-data *

echo "ownership fixed"

git lfs install
git lfs track *.rar

echo "large files tracked"

systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"
