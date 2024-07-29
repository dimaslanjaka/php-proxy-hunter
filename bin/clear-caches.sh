#!/bin/bash

# clear VPS memory every day at 3 AM
# 0 3 * * * /var/www/html/bin/clear_caches.sh

sync
echo 3 > /proc/sys/vm/drop_caches

# Clear old logs
sudo du -sh /var/log/*
sudo rm -f /var/log/*.gz

# Clear trash
rm -rf ~/.local/share/Trash/*

# show usages
echo "Disk Status:"
df -h
echo "RAM Status:"
free -h