# PHP 8.1 Installation Guide

This document is the PHP 8.1 entry point and keeps the legacy guide unchanged.

- Legacy guide (unchanged): [readme-php7.4.md](./readme-php7.4.md)
- Full PHP 8.1 setup and troubleshooting: [../readme-php.md](../readme-php.md)

## Quick Install (Ubuntu)

```bash
sudo apt update -y
sudo apt install -y php8.1 php8.1-common php8.1-cli php8.1-opcache php8.1-gd php8.1-curl php8.1-mysql php8.1-sqlite3
```

## Restart Services

```bash
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

## Notes

- If you compile PHP from source or need `pdo_sqlite` troubleshooting, follow the complete instructions in [../readme-php.md](../readme-php.md).
- This file exists so PHP 8.1 documentation can evolve independently from the PHP 7.4 guide.
