# php-proxy-hunter

PHP proxy hunter | PHP proxy checker | Python proxy hunter | Python proxy checker

**CI**

![GitHub Actions Workflow Status - PHP](https://img.shields.io/github/actions/workflow/status/dimaslanjaka/php-proxy-hunter/.github%2Fworkflows%2Fchecker-php.yml?branch=master&style=for-the-badge&label=proxy%20checker%20PHP&labelColor=blue&link=https%3A%2F%2Fgithub.com%2Fdimaslanjaka%2Fphp-proxy-hunter%2Factions)
![GitHub Actions Workflow Status - Python](https://img.shields.io/github/actions/workflow/status/dimaslanjaka/php-proxy-hunter/.github%2Fworkflows%2Fchecker-python.yml?branch=master&style=for-the-badge&label=proxy%20checker%20Python&labelColor=blue&link=https%3A%2F%2Fgithub.com%2Fdimaslanjaka%2Fphp-proxy-hunter%2Factions)
<!-- red %23b81220 -->

**Servers**

[![](https://img.shields.io/badge/MAINTENANCE-PHP%20SERVER-blue?style=for-the-badge&labelColor=green&color=blue&label=RUNNING)](https://sh.webmanajemen.com)
[![](https://img.shields.io/badge/MAINTENANCE-PYTHON%20SERVER-blue?style=for-the-badge&labelColor=green&color=blue&label=RUNNING)](https://sh.webmanajemen.com:8443)

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
- Proxy Manager via WhatsApp Bot

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

- [READ PHP SETUP](readme-php.md)
- [READ PYTHON SETUP](readme-python.md)
- [READ NODEJS SETUP](readme-nodejs.md)

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

extract `src/database.rar` to `src` folder

> The result like below tree

```
.
└── Working directory/
    ├── src/
    │   ├── database.sqlite
    │   └── database.sqlite-whm
    ├── proxies.txt
    └── dead.txt
```

- modify `.htaccess` or `nginx.conf` with your domain
- install dependencies

```bash
yarn install
python3 requirements_install.py
php composer.phar install
```

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
0 0 * * 0 php /var/www/html/cleaner.php
```

backup database everyday at midnight

```bash
0 0 * * * sqlite3 /var/www/html/src/database.sqlite .dump > /var/www/html/backups/database_backup_$(date +\%Y-\%m-\%d).sql
```

### crontab using spesific user

> edit `www-data` with your username

```bash
# edit crontab
sudo crontab -u www-data -e
# list crontab
sudo crontab -u www-data -l
# apply crontab from file (.crontab.txt)
sudo crontab -u www-data .crontab.txt
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

