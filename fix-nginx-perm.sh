#!/bin/bash

sudo chown -R www-data:www-data *
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