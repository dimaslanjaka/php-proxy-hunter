# Documentation for PHP Proxy Hunter

> Old version:
>
> [PHP 7.4 installation guide](./docs/readme-php7.4.md)

## Requirement PHP extensions

- pdo_sqlite
- php_zip
- php_intl

## Install required libraries

```bash
sudo apt update -y
sudo apt install -y autoconf bison build-essential curl libbz2-dev libcurl4-openssl-dev libfreetype6-dev libicu-dev libjpeg-dev libjpeg8-dev libmcrypt-dev libmysqlclient-dev libonig-dev libpng-dev libpng16-16 libpq-dev libpspell-dev libreadline-dev libsodium-dev libssl-dev libtool libwebp-dev libxml2-dev libxpm-dev libxslt1-dev libzip-dev perl pkg-config re2c unzip wget zlib1g-dev
```

> `sqlite3`, `libsqlite3-dev` install when you not build sqlite from source

### Install php in ubuntu from source

```bash
echo "/usr/local/lib" | sudo tee /etc/ld.so.conf.d/local-sqlite.conf
sudo ldconfig

export CPPFLAGS="-I/usr/local/include"
export LDFLAGS="-L/usr/local/lib"
export LD_LIBRARY_PATH="/usr/local/lib"

git clone https://github.com/php/php-src.git --depth 1 --branch php-8.4.11
cd php-src

# cleanup builds
make clean
make distclean
./buildconf --force

./configure --prefix=/usr/local/php --with-config-file-path=/usr/local/php/etc --enable-bcmath --enable-calendar --enable-exif --enable-ftp=shared --enable-intl --enable-mbstring --enable-soap --enable-sockets --enable-sysvmsg --enable-sysvsem --enable-sysvshm --with-curl --with-libdir=/lib/x86_64-linux-gnu --with-mysqli --with-openssl --with-pdo-mysql --with-pdo-sqlite --with-sqlite3 --with-readline --with-libxml --with-zlib --with-sodium --with-zip --with-bz2 --enable-fpm

make -j $(nproc)
sudo make install

# verify installation
/usr/local/php/bin/php -v
/usr/local/php/sbin/php-fpm -v

# bind to system
sudo ln -sf /usr/local/php/bin/php /usr/bin/php

# copy php-fpm
cp sapi/fpm/php-fpm.conf /usr/local/php/etc/php-fpm.conf
mkdir -p /usr/local/php/etc/php-fpm.d
cp sapi/fpm/www.conf /usr/local/php/etc/php-fpm.d/
cp sapi/fpm/php-fpm /usr/local/bin/php-fpm
chmod +x /usr/local/bin/php-fpm
sudo cp sapi/fpm/php-fpm.service /etc/systemd/system/php-fpm.service
```

#### Configure php-fpm (if applicable)

see [/assets/systemctl](/assets/systemctl) for the configs

> editing `php-fpm.service` carefully

```bash
sudo mkdir -p /run/php
sudo chown -R www-data:www-data /run/php
sudo chown -R www-data:www-data /usr/local/php/var/log

# test
/usr/local/php/sbin/php-fpm --test --fpm-config /usr/local/php/etc/php-fpm.conf
/usr/local/php/sbin/php-fpm --nodaemonize --fpm-config /usr/local/php/etc/php-fpm.conf

# enable php-fpm
sudo systemctl daemon-reload
systemctl reset-failed php-fpm
sudo systemctl enable php-fpm
sudo systemctl start php-fpm
sudo systemctl status php-fpm
```

see [.htaccess_nginx](./.htaccess_nginx) to modify nginx config in `/etc/nginx/sites-enabled/default`

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

### Troubleshoot `sqlite3_column_table_name` error

If you see an error like:

```text
/usr/bin/ld: ext/pdo_sqlite/.libs/sqlite_statement.o: in function `pdo_sqlite_stmt_col_meta':
/usr/local/src/php-src-php-7.4.33/ext/pdo_sqlite/sqlite_statement.c:379: undefined reference to `sqlite3_column_table_name'
collect2: error: ld returned 1 exit status
make: *** [Makefile:288: sapi/cli/php] Error 1
```

This means PHP is not linking against the correct (new) SQLite library. To fix:

1. Ensure you have a recent SQLite (3.7.15+):
   ```bash
   sqlite3 --version
   # Should be >= 3.7.15
   ```
2. Make sure the linker/compiler use the correct SQLite:
   ```bash
   echo "/usr/local/lib" | sudo tee /etc/ld.so.conf.d/local-sqlite.conf
   sudo ldconfig
   export CPPFLAGS="-I/usr/local/include"
   export LDFLAGS="-L/usr/local/lib"
   export LD_LIBRARY_PATH="/usr/local/lib"
   ```
3. Clean and rebuild PHP:
   ```bash
   make clean
   make distclean
   ./buildconf --force
   ./configure --prefix=/usr/local/php7.4 ...your options...
   make -j$(nproc)
   ```
4. Check linkage:
   ```bash
   ldd sapi/cli/php | grep sqlite
   # Should show /usr/local/lib/libsqlite3.so.0
   ```

If it still fails, [build the sqlite from source](./readme-sqlite.md)

```bash
sudo apt remove -y --purge sqlite3 libsqlite3-dev
sudo apt autoremove -y
cd /usr/local/src
wget https://www.sqlite.org/2023/sqlite-autoconf-3410200.tar.gz
tar xvzf sqlite-autoconf-3410200.tar.gz
cd sqlite-autoconf-3410200/
CFLAGS="-O2 -DSQLITE_ENABLE_COLUMN_METADATA=1" ./configure
make
sudo make install
```

another sqlite versions:

- https://sqlite.org/2025/sqlite-src-3500400.zip

### Troubleshoot pdo_sqlite.so error

> change **php-7.2.24** to your php version (`php -v`)

1. rebuild

```bash
mkdir ~/php-src
cd ~/php-src
wget https://www.php.net/distributions/php-7.2.24.tar.gz
tar -zxvf php-7.2.24.tar.gz
cd php-7.2.24
cd ext/pdo_sqlite
phpize
./configure
make
```

2. get destination directory

> get your existing `extension directory`

```bash
php -i | grep "extension_dir"
```

3. copy extension

copy the destination folder. eg: **/usr/lib/php/20170718/**

```bash
cd ~/php-src/php-7.2.24/ext/pdo_sqlite/modules/
sudo cp pdo_sqlite.so /usr/lib/php/20170718/
```

4. restart

```bash
ls -l /usr/lib/php/20170718/pdo_sqlite.so
sudo systemctl restart php7.2-fpm # Replace with your PHP-FPM version
sudo systemctl restart nginx      # for nginx
```

### Troubleshoot php-fpm immediate restart

> when your php-fpm immediately shutdown you can fix with this

Edit `/etc/php/7.2/fpm/php-fpm.conf` or `/etc/php/7.2/fpm/pool.d/www.conf`:

```ini
emergency_restart_threshold = 10
emergency_restart_interval = 1m
```

### Troubleshoot nginx with php-fpm

> fix unrecognized sock listen

```ini
# pass PHP scripts to FastCGI server
#
location ~ \.php$ {
    include snippets/fastcgi-php.conf;

    # changa path value from `/etc/php/7.2/fpm/php-fpm.conf` or `/etc/php/7.2/fpm/pool.d/www.conf`
    fastcgi_pass unix:/run/php/php7.2-fpm.sock;
}
```

### Troubleshoot replace apache2 with nginx

Uninstall apache2

```bash
sudo systemctl stop apache2
sudo systemctl disable apache2
sudo apt-get remove --purge apache2 apache2-utils apache2-bin apache2.2-common apache2-dev -y
sudo apt-get autoremove --purge -y
sudo apt-get clean
```

Install nginx

```bash
sudo apt update -y
sudo apt install nginx -y
sudo systemctl start nginx
sudo systemctl enable nginx
```

Configure firewall (if applicable)

```bash
sudo ufw allow 'Nginx Full'
```

> when `ufw` command not found, dont worry continue next step

Verify nginx installation

```bash
sudo systemctl status nginx
sudo nginx -t
```

> Now you can modify nginx config

Restart nginx

```bash
sudo systemctl restart nginx
```

#### Configure

- rename **.env_sample** to **.env** and edit with your data
- edit your nginx settings based on **.htaccess_nginx.conf**
