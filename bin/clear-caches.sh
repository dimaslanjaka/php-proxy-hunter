#!/bin/bash

# clear VPS memory every day at 3 AM
# 0 3 * * * /var/www/html/bin/clear_caches.sh

sync
echo 3 > /proc/sys/vm/drop_caches
