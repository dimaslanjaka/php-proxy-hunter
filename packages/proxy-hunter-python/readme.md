# ProxyHunter v2

Author: dimaslanjaka@gmail.com
Original Author: aaron.nelson805@gmail.com
Repository: https://github.com/xajnx/proxyhunter

---

## Installation

Add this to your `requirements.txt`:

```
git+https://github.com/dimaslanjaka/php-proxy-hunter.git#subdirectory=packages/proxy-hunter-python
```

Then install:

```
pip install -r requirements.txt --force-reinstall
```

---

## Description

ProxyHunter v2 is a CLI Python 3 script that uses the `ip_ranges_US.txt` list of US IP ranges to search for open Squid proxy servers.

You can also scan for other countries by visiting:
http://www.ipaddresslocation.org/ip_ranges/get_ranges.php
Select your country, choose **CIDR** format, and download the ranges to your `proxyhunter` directory.

Unlike tools that rely on external proxy lists, ProxyHunter actively scans IP ranges.
By default, it randomly selects **2,500 IP ranges** and searches until it finds **25 open proxies**.
Optional MAC address spoofing is supported if your network adapter allows it (requires root access and an external script).

---

## Features

- Scans most popular proxy ports (more can be added)
- Retrieves proxies from random IP ranges for the country of your choice (default: US)
- Writes results to a file, making it easy to paste into `proxychains.conf`
- Can scan:
  - Single IP (e.g., `192.168.2.1`)
  - IP range (e.g., `192.168.2.0/24`)

---

## Planned Features

- Command-line arguments to change variables such as socket timeout and scan mode (single IP or IP range)
- Options to increase or decrease the number of found proxies before exit
- Attempts to connect to test sites to check if proxies are valid
- More scanning capabilities

---

## Requirements

If you don’t have these libraries installed, run:

```
pip3 install <library>
# or
sudo -H pip3 install <library>
```

Dependencies:
- sys
- socket
- colorama
- netaddr
- time
- random
- os
- pause
- MACSpoof ([SpoofMAC GitHub](https://github.com/feross/SpoofMAC))

---

## Usage

Basic usage:

```
python3 proxyhunter.py
```

With MAC spoofing (requires root):

```
sudo python3 proxyhunter.py
```

---

## Example Output

```
skywalker@endor:~/scripts/python/proxyhunter$ python3 proxyhunter.py
_-=-__-=-__-=-__-=-__-=-_
    Proxy Hunter v2
_-=-__-=-__-=-__-=-__-=-_

Would you like to spoof your MAC address?(y/n): n

Initializing scanner..
Please wait this may take some time.
104.236.27.0/24: 256 available IPs
Checking host: 104.236.27.2
104.236.27.2:80 is OPEN
no proxy
Checking host: 104.236.27.6
104.236.27.6:80 is OPEN
Service: Socks
Saving..
104.236.27.6:81 is OPEN
no proxy
104.236.27.6:3128 is OPEN
Service: Socks
Saving..
104.236.27.6:8080 is OPEN
Service: Squid
Saving..
Checking host: 104.236.27.7
104.236.27.7:80 is OPEN
....
```

---


## Develop

to build:

```bash
pip install build
pip -m build
```

to install locally:

```bash
pip install -e .
```
