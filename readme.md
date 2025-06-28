# PHP Proxy Hunter

> A cross-platform proxy checker and hunter toolkit built with PHP, Python, and shell scripting.

[![PHP Checker CI](https://img.shields.io/github/actions/workflow/status/dimaslanjaka/php-proxy-hunter/.github%2Fworkflows%2Fchecker-php.yml?branch=master&style=for-the-badge&label=PHP%20Checker&labelColor=blue)](https://github.com/dimaslanjaka/php-proxy-hunter/actions)
[![Python Checker CI](https://img.shields.io/github/actions/workflow/status/dimaslanjaka/php-proxy-hunter/.github%2Fworkflows%2Fchecker-python.yml?branch=master&style=for-the-badge&label=Python%20Checker&labelColor=blue)](https://github.com/dimaslanjaka/php-proxy-hunter/actions)

### Live Servers

[![PHP Server](https://img.shields.io/badge/MAINTENANCE-PHP%20SERVER-blue?style=for-the-badge&labelColor=green)](https://sh.webmanajemen.com)
[![Python Server](https://img.shields.io/badge/MAINTENANCE-PYTHON%20SERVER-blue?style=for-the-badge&labelColor=green)](https://sh.webmanajemen.com:8443)

---

## 🚀 Features

- Proxy checker/hunter/extractor
- CIDR range scanner
- HTTP/HTTPS, SOCKS4/5 support
- Open port scanner
- Web-based and CLI interface
- Artisan, Nginx, and Apache support
- Multithread & single-thread checker
- WhatsApp Bot proxy manager (Ubuntu 20.x)
- Cross-platform: Linux & Windows 10+
- PHP, Python, Bash, Batch support

<img src="https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/d24b8bbf-0fa0-4394-b9e7-78350bdda67d" width="100%" />
<img src="https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/1e52d0f8-6417-41c3-bb75-86009726df7d" width="100%" />
<img src="https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/adb9aaaf-2151-44c0-aaf2-b19c1c536bc7" width="100%" />

---

## 📦 Requirements

Before starting, run:

```bash
git submodule update --init --recursive
sudo apt install build-essential autoconf libtool bison re2c pkg-config -y
```

### Install SQLite 3.46

**Ubuntu 18.x:**

```bash
# Build from source
cd /tmp
curl -LO https://www.sqlite.org/2024/sqlite-autoconf-3460000.tar.gz
tar -xzf sqlite-autoconf-3460000.tar.gz
cd sqlite-autoconf-3460000
./configure && make && sudo make install
export LD_LIBRARY_PATH=/usr/local/lib:$LD_LIBRARY_PATH
export LD_RUN_PATH=/usr/local/lib:$LD_RUN_PATH

# Update ld.so
if ! grep -q "/usr/local/lib" /etc/ld.so.conf; then
    echo "/usr/local/lib" | sudo tee -a /etc/ld.so.conf
fi
sudo ldconfig

# Link binary
sudo ln -sf /usr/local/bin/sqlite3 /usr/bin/sqlite3
sqlite3 --version
```

**Ubuntu 20.x:**

```bash
sudo apt update && sudo apt upgrade
sudo apt install sqlite3
sqlite3 --version
```

---

## 📖 Setup Guides

- [PHP Setup Guide](readme-php.md)
- [Python Setup Guide](readme-python.md)
- [Node.js Setup Guide](readme-nodejs.md)

---

## ⚙️ Installation

```bash
python3 requirements_install.py
yarn install
composer install

# Setup Git attributes merge driver
git config merge.resolve_hash.driver "node bin/create-file-hashes.cjs %O %A %B"
```

### Build Project

```bash
task build
```

---

## 🧪 Quickstart

```bash
sudo apt install git git-lfs -y
git clone https://github.com/dimaslanjaka/php-proxy-hunter my-project
cd my-project
git lfs install
git lfs track "*.rar"
```

1. Rename `.env_sample` to `.env`
2. Create required data files:

```bash
touch CIDR.txt CIDR-original.txt dead.txt proxies.txt proxies-all.txt proxies-http.txt proxies-socks.txt proxyChecker.txt proxyFetcherSources.txt proxyRange.txt status.txt working.txt
```

3. Extract `src/database.rar` into `src/`

```text
.
└── project/
    ├── src/
    │   ├── database.sqlite
    │   └── database.sqlite-whm
    ├── proxies.txt
    └── dead.txt
```

4. Modify `.htaccess` or `nginx.conf` for your domain

5. Install dependencies:

```bash
npm install -g @go-task/cli
task install-nodejs
task install-python
task install-php
```

---

## ⏰ Crontab Jobs

| Task                        | Schedule            | Command |
|----------------------------|---------------------|---------|
| Proxy checker              | Every 10 mins       | `*/10 * * * * php /path/to/proxyChecker.php > /path/to/proxyChecker.txt 2>&1` |
| Proxy fetcher              | Daily               | `0 0 * * * php /var/www/html/proxyFetcher.php` |
| Proxy index                | Daily               | `0 0 * * * php /var/www/html/proxies-all.php` |
| Parallel checker           | Hourly at 17 mins   | `17 */1 * * * php /var/www/html/proxyCheckerParallel.php` |
| Cleaner                    | Weekly (Sunday)     | `0 0 * * 0 php /var/www/html/cleaner.php` |
| DB backup                  | Daily at midnight   | `0 0 * * * sqlite3 /var/www/html/src/database.sqlite .dump > /var/www/html/backups/database_backup_$(date +\%Y-\%m-\%d).sql` |

**Crontab as specific user:**

```bash
sudo crontab -u www-data -e    # Edit
sudo crontab -u www-data -l    # List
sudo crontab -u www-data .crontab.txt  # Apply from file
```

---

## 🛠️ Troubleshooting

### Missing PHP Extension

```bash
sudo apt install php-fpm -y
```

### Git Permissions Issue

```bash
git config core.fileMode false
```

### Git LFS Quota Exceeded

```bash
GIT_LFS_SKIP_SMUDGE=1
```

### Restart PHP/Nginx

```bash
systemctl restart php7.2-fpm
systemctl restart nginx
```

---

## 🐍 Python

```bash
python3 -m pip install -r requirements.txt
```

---

## 📦 Production (Windows)

- [Download PHP](https://windows.php.net/downloads/releases/archives/)
- [Download Chrome & WebDriver](https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json)

Place them in the following structure:

```text
assets/
├── php/
│   └── php.exe
├── chrome/
    ├── chrome.exe
    └── chromedriver.exe
```

Update `php.ini`:

```ini
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

---

## 📄 License

```
This project is licensed under the GNU General Public License v3.0
Copyright (c) 2024 Dimas Lanjaka

See https://www.gnu.org/licenses/gpl-3.0.html for details.
```

For questions:

- **Name:** Dimas Lanjaka
- **Website:** [webmanajemen.com](https://www.webmanajemen.com)
- **Email:** dimaslanjaka@gmail.com
