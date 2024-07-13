# php-proxy-hunter

PHP proxy hunter | PHP proxy checker | Python proxy hunter | Python proxy checker

[![](https://img.shields.io/badge/MAINTENANCE-DEPLOYMENT%20STATUS-blue?style=for-the-badge&labelColor=%23b81220&color=blue&label=MAINTENANCE)](https://sh.webmanajemen.com)

## Features

- Lightweight
- HTTP/HTTPS/SSL supported
- SOCKS4/5 supported
- CIDR ranges scanner
- Proxy extractor
- Single thread proxy checker
- Multi thread proxy checker
- Open port scanner
- Artisan supported
- Web server supported
- Nginx supported
- Apache supported
- Proxy finder
- Proxy hunter
- Proxy checker
- Python proxy checker available (Converted)
- Bash available
- Batch available

![image](https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/d24b8bbf-0fa0-4394-b9e7-78350bdda67d)
![image](https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/1e52d0f8-6417-41c3-bb75-86009726df7d)
![image](https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/adb9aaaf-2151-44c0-aaf2-b19c1c536bc7)

## Requirements

> Before all, run
```bash
git submodule update -i -r
sudo apt install build-essential autoconf libtool bison re2c pkg-config -y
```

### Install sqlite 3.46 in ubuntu

```bash
cd /tmp
curl -L -O https://www.sqlite.org/2024/sqlite-autoconf-3460000.tar.gz
tar -xzf sqlite-autoconf-3460000.tar.gz
cd sqlite-autoconf-3460000
./configure
make
sudo make install
export LD_LIBRARY_PATH=/usr/local/lib:$LD_LIBRARY_PATH
export LD_RUN_PATH=/usr/local/lib:$LD_RUN_PATH
# Check if /usr/local/lib is in /etc/ld.so.conf
if ! grep -q "/usr/local/lib" /etc/ld.so.conf; then
    echo "/usr/local/lib" | sudo tee -a /etc/ld.so.conf
    sudo ldconfig
    echo "/usr/local/lib added to /etc/ld.so.conf and ldconfig updated."
else
    echo "/usr/local/lib already exists in /etc/ld.so.conf"
    sudo ldconfig
fi
sudo ln -sf /usr/local/bin/sqlite3 /usr/bin/sqlite3
ls -l /usr/bin/sqlite3
# verify installation
which sqlite3
sqlite3 --version
```

### Install php7.4 in ubuntu

- pdo_sqlite
- php_zip
- php_intl

```bash
cd /tmp
sudo apt install -y unzip libicu-dev wget build-essential libxml2-dev libssl-dev libcurl4-openssl-dev libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libsqlite3-dev libbz2-dev libreadline-dev pkg-config autoconf bison re2c zlib1g-dev libxslt1-dev libwebp-dev libpq-dev libsodium-dev
export LD_LIBRARY_PATH=/usr/local/lib:/usr/lib/x86_64-linux-gnu

# Install from dist
# wget https://www.php.net/distributions/php-7.4.30.tar.gz
# tar -zxvf php-7.4.30.tar.gz
# cd php-7.4.30

# Install from github
# git clone --depth 1 --branch=master https://github.com/php/php-src php-src
wget https://github.com/php/php-src/archive/refs/tags/php-7.4.33.tar.gz
tar -zxvf php-7.4.33.tar.gz
cd php-src-php-7.4.33
./buildconf --force

# configuring makefile
./configure --prefix=/usr/local/php7.4 --with-config-file-path=/usr/local/php7.4/etc --enable-bcmath --enable-calendar --enable-exif --enable-ftp=shared --enable-intl --enable-mbstring --enable-soap --enable-sockets --enable-sysvmsg --enable-sysvsem --enable-sysvshm --with-curl --with-libdir=/lib/x86_64-linux-gnu --with-mysqli --with-openssl --with-pdo-mysql --with-pdo-sqlite --with-sqlite3 --with-readline --with-libxml --with-zlib --with-sodium --with-zip --with-bz2 --enable-fpm
make -j $(nproc)
sudo make install

# verify installation
/usr/local/php7.4/bin/php -v
/usr/local/php7.4/sbin/php-fpm -v
```

### Setup php-fpm

```bash
mkdir -p /etc/php/7.4/fpm/pool.d
cp -r .php-fpm.conf /etc/php/7.4/fpm/pool.d/www.conf
```

### php functions

> ensure these functions are activated on your server
>
> **Required for background tasking**

- shell_exec
- exec
- popen

### php.ini configuration

> When you running on **windows** you'll need configure **php.ini** file

```ini
;suppress inspection "DuplicateKeyInSection" for whole file
; uncomment below codes from php.ini
extension_dir = "ext"
extension = pdo_sqlite
extension = curl
extension = openssl
extension = mbstring
extension = intl
extension = xmlrpc
extension = fileinfo
extension = sockets
extension = xsl
extension = exif
extension = gettext
extension = ftp
```

## Python requirements

> major packages needed

```bash
sudo apt-get update -y
sudo apt-get install build-essential gdb lcov pkg-config libcurl4-openssl-dev libbz2-dev libffi-dev libgdbm-dev libgdbm-compat-dev liblzma-dev libncurses5-dev libreadline6-dev libsqlite3-dev libssl-dev curl lzma tk-dev uuid-dev zlib1g-dev software-properties-common -y
```

### Install python 3.11 in ubuntu

```bash
sudo add-apt-repository ppa:deadsnakes/ppa
sudo apt update -y
sudo apt install python3.11
```

when above not working try

### Install python 3.11 from source in ubuntu

```bash
cd /tmp
curl -L https://www.python.org/ftp/python/3.11.9/Python-3.11.9.tar.xz -o python3.11.tar.xz
tar -xf python3.11.tar.xz
cd /tmp/Python-3.11.9
./configure
make
sudo make install
```

### Initialize virtual environtment python 3.11 on ubuntu

#### Install

```bash
python3.11 -m venv venv
# OR run using spesific user
sudo -u www-data -H bash -c "python3.11 -m venv /var/www/html/venv"
```

#### Usage

```bash
source venv/bin/activate
pip install --upgrade pip
python requirements_install.py

# OR run using spesific user
sudo -u www-data -H bash -c "source /var/www/html/venv/bin/activate && bash"
sudo chown www-data:www-data /var/www/venv
sudo -u www-data -H bash -c "source /var/www/html/venv/bin/activate && pip install --upgrade pip"
sudo -u www-data -H bash -c "source /var/www/html/venv/bin/activate && python /var/www/html/requirements_install.py"
```

## Quickstart

- clone repository

```bash
sudo apt-get install git git-lfs -y
git clone https://github.com/dimaslanjaka/php-proxy-hunter folder_name
cd folder_name
git lfs install
git lfs track *.rar
```

- rename `.env_sample` into `.env`

- create database files

```bash
touch CIDR.txt CIDR-original.txt dead.txt proxies.txt proxies-all.txt proxies-http.txt proxies-socks.txt proxyChecker.txt proxyFetcherSources.txt proxyRange.txt status.txt working.txt
```

- use **WinRAR** to extract **sqlite** database chunks in **src** folder into single **database.sqlite** file

- modify `.htaccess` or `nginx.conf` with your domain

## Crontab

run proxy checker every 10 mins

```bash
*/10 * * * * php /path/to/proxyChecker.php > /path/to/proxyChecker.txt 2>&1
```

run proxy fetcher every day once

```bash
0 0 * * * php /var/www/html/proxyFetcher.php
```

run proxy indexing every day once

```bash
0 0 * * * php /var/www/html/proxies-all.php
```

run proxy checker parallel every 1 hour 17 minutes

```bash
17 */1 * * * php /var/www/html/proxyCheckerParallel.php
```

run cleaner every week

```
0 0 * * 0 php /var/www/html/configCleaner.php
```

to run crontab using spesific user

```bash
crontab -u www-data -e
```

to list spesific user crontab

```bash
crontab -u www-data -l
```

## Troubleshoot

<!-- missing php extension -->

- To run webserver for nginx needs **php-fpm** install using `sudo apt install php-fpm -y`
- To disable git indexing when changing permission files using **chmod** run `git config core.fileMode false`

### Restart php

```sh
systemctl restart php7.2-fpm
systemctl restart nginx
```

## Python

### Requirements

- python v3

### Quick Start

```bash
python -m pip install -r requirements.txt
```

## Production

- [download php here](https://windows.php.net/downloads/releases/archives/)
- [download chrome and webdriver here](https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json)
- extract [php zip](https://windows.php.net/downloads/releases/archives/php-7.4.3-nts-Win32-vc15-x86.zip) into **assets/php**. The file structure should be **assets/php/php.exe**
- extract [chrome zip](https://storage.googleapis.com/chrome-for-testing-public/124.0.6367.91/win32/chrome-win32.zip) into **assets/chrome**. The file structure should be **assets/chrome/chrome.exe**
- extract [chrome driver zip](https://storage.googleapis.com/chrome-for-testing-public/124.0.6367.91/win32/chromedriver-win32.zip) into **assets/chrome**. The file structure should be **assets/chrome/chromedriver.exe**
- rename **php.ini-production** to **php.ini**, then modify **php.ini**

```ini
; uncomment below codes from php.ini
extension_dir = "ext"
extension=pdo_sqlite
extension=curl
extension=openssl
extension=mbstring
extension=intl
extension=xmlrpc
extension=fileinfo
extension=sockets
extension=xsl
extension=exif
extension=gettext
extension=ftp
```

```txt
----------------------------------------------------------------------------
LICENSE
----------------------------------------------------------------------------
This file is part of Proxy Checker.

Proxy Checker is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Proxy Checker is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Proxy Checker.  If not, see <https://www.gnu.org/licenses/>.

----------------------------------------------------------------------------
Copyright (c) 2024 Dimas lanjaka
----------------------------------------------------------------------------
This project is licensed under the GNU General Public License v3.0
For full license details, please visit: https://www.gnu.org/licenses/gpl-3.0.html

If you have any inquiries regarding the license or permissions, please contact:

Name: Dimas Lanjaka
Website: https://www.webmanajemen.com
Email: dimaslanjaka@gmail.com
```

