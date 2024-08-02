#!/bin/bash

# Define log file locations
DATE=$(date +'%Y-%m-%d')
LOG_DIR="/var/www/html/logs"
LOG_FILE="$LOG_DIR/huey-$DATE.log"

# Ensure the log directory exists and is writable
mkdir -p $LOG_DIR
chown www-data:www-data $LOG_DIR

# Start the Huey worker with built-in logging and redirect output to the specified log file
exec /var/www/html/venv/bin/python /var/www/html/manage.py run_huey --logfile $LOG_FILE &
