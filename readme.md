# php-proxy-hunter

PHP proxy hunter

![image](https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/d24b8bbf-0fa0-4394-b9e7-78350bdda67d)
![image](https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/1e52d0f8-6417-41c3-bb75-86009726df7d)
![image](https://github.com/dimaslanjaka/php-proxy-hunter/assets/12471057/adb9aaaf-2151-44c0-aaf2-b19c1c536bc7)

## Requirements

- pdo_sqlite `sudo apt install php7.2-sqlite3`

## Quickstart

- create database files

```bash
touch CIDR.txt CIDR-original.txt dead.txt proxies.txt proxies-all.txt proxies-http.txt proxies-socks.txt proxyChecker.txt proxyFetcherSources.txt proxyRange.txt socks.txt socks-dead.txt socks-working.txt status.txt working.txt
```

- use **WinRAR** to extract **sqlite** database chunks in **src** folder into single **database.sqlite** file

## Troubleshoot

<!-- missing php extension -->

- To run webserver for nginx needs **php-fpm** install using `sudo apt install php-fpm -y`
- To disable git indexing when changing permission files using **chmod** run `git config core.fileMode false`

### Restart php

```sh
systemctl restart php7.2-fpm
systemctl restart nginx
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

