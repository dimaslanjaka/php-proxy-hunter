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
sudo chown -R www-data:www-data tmp/*
sudo chown -R www-data:www-data tmp/cookies/*
sudo chmod 777 *.txt
sudo chmod 777 config
sudo chmod 777 tmp
sudo chmod 777 .cache
sudo chmod 644 *.php
# sudo chown -R www-data:www-data /var/www/html

echo "permission sets successful"
echo ""

su -s /bin/bash -c 'php composer.phar install' www-data
# php composer.phar install
sudo chown -R www-data:www-data vendor/*
sudo chown -R www-data:www-data vendor/composer/*

echo "composer installed"