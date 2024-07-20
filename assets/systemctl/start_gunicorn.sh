#!/bin/bash

DATE=$(date +'%Y-%m-%d')
LOG_DIR=/var/www/html/tmp/logs
ACCESS_LOGFILE=$LOG_DIR/gunicorn-access-$DATE.log
ERROR_LOGFILE=$LOG_DIR/gunicorn-error-$DATE.log

mkdir -p $LOG_DIR

exec /var/www/html/venv/bin/gunicorn --workers 3 --bind unix:/var/www/html/tmp/gunicorn.sock django_backend.wsgi:application --access-logfile $ACCESS_LOGFILE --error-logfile $ERROR_LOGFILE
