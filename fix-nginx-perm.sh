#!/bin/bash

sudo chown -R www-data:www-data *
sudo chmod 777 *.txt
sudo chmod 777 config
sudo chmod 777 .cache
sudo chmod 755 *.php
sudo chown -R www-data:www-data /var/www/html
# systemctl restart php7.2-fpm

echo "permission sets successful"