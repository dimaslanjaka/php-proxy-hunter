#!/bin/bash

sudo chown -R www-data:www-data *
sudo chown -R www-data:www-data src/*
sudo chown -R www-data:www-data config/*
sudo chown -R www-data:www-data .cache/*
sudo chown -R www-data:www-data tmp/*
mkdir -p tmp/cookies
touch tmp/cookies/index.html
touch tmp/index.html
mkdir -p config
touch config/index.html
sudo chown -R www-data:www-data data/*
sudo chown -R www-data:www-data tmp/*
sudo chown -R www-data:www-data tmp/cookies/*
sudo chmod 777 *.txt
sudo chmod 777 config
sudo chmod 777 tmp
sudo chmod 777 .cache
sudo chmod 777 data
sudo chmod 777 data/*.pem
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