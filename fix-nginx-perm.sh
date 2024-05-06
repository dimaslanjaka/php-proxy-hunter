#!/bin/bash
# shellcheck disable=SC2035

rm *.lock


mkdir -p tmp/cookies
touch tmp/cookies/index.html
touch tmp/index.html
mkdir -p config
touch config/index.html

sudo chown -R www-data:www-data *
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
# sudo chown -R www-data:www-data /var/www/html

echo "permission sets successful"

su -s /bin/bash -c 'php composer.phar install' www-data
su -s /bin/bash -c 'php composer.phar update' www-data
# php composer.phar install
sudo chown -R www-data:www-data vendor/*
sudo chown -R www-data:www-data vendor/composer/*
sudo chown -R www-data:www-data vendor/annexare/countries-list/dist/*
sudo chown -R www-data:www-data vendor/annexare/countries-list/dist/minimal/*
sudo chown -R www-data:www-data vendor/annexare/countries-list/dist/cjs/*
sudo chown -R www-data:www-data vendor/annexare/countries-list/dist/mjs/*

echo "composer installed"

git lfs install
git lfs track *.rar

echo "large files tracked"

systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"