#!/bin/bash

sudo chown -R www-data:www-data *
sudo chmod 777 *.txt
sudo chmod 777 config
sudo chmod 777 .cache
sudo chmod 644 *.php
# sudo chown -R www-data:www-data /var/www/html

echo "permission sets successful"