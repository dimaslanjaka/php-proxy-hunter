#!/bin/sh

# shellcheck disable=SC2035

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
    sudo chmod 755 "assets/proxies"
    touch "assets/proxies/index.html"
fi

# to allow composer and indexing proxies work
sudo chown -R www-data:www-data *.php *.phar

echo "permission sets successful"

OUTPUT_FILE="/var/www/html/proxyChecker.txt"

printf "Are you want to re-install composer? [Y/n] "
read -r response
response=$(echo "$response" | tr '[:upper:]' '[:lower:]')
if [ "$response" = "y" ]; then
  COMPOSER_LOCK="/var/www/html/composer.lock"
  COMPOSER_PHAR="/var/www/html/composer.phar"

  if [ ! -f "$COMPOSER_LOCK" ]; then
      su -s /bin/sh -c "php $COMPOSER_PHAR install >> $OUTPUT_FILE 2>&1" www-data
  else
      su -s /bin/sh -c "php $COMPOSER_PHAR update >> $OUTPUT_FILE 2>&1" www-data
  fi
fi

printf "Are you want to re-index all proxies? [Y/n] "
read -r response
response=$(echo "$response" | tr '[:upper:]' '[:lower:]')
if [ "$response" = "y" ]; then
  su -s /bin/sh -c "php /var/www/html/proxies-all.php >> $OUTPUT_FILE 2>&1 &" www-data
fi

chmod 777 vendor
touch vendor/index.html

echo "composer installed"

sudo chown -R www-data:www-data *.php *.txt *.json *.js *.html src data tmp vendor assets
sudo chown -R www-data:www-data .cache config *.css *.lock js .htaccess .env

echo "ownership fixed"

#git lfs install
#git lfs track *.rar

echo "large files tracked"

systemctl restart php7.2-fpm
systemctl restart nginx

echo "nginx and php-fpm restarted"
