#!/bin/bash

# Set timezone to Asia/Jakarta
timezone_file="/usr/share/zoneinfo/Asia/Jakarta"
link_target="/etc/localtime"

# Check if the timezone file exists
if [ -f "$timezone_file" ]; then
    # Create symbolic link with sudo privileges
    sudo ln -sf "$timezone_file" "$link_target"
    echo "Symbolic link created successfully."
else
    echo "Error: Timezone file $timezone_file not found."
fi
sudo timedatectl set-timezone Asia/Jakarta
echo "Timezone set to $(date)"