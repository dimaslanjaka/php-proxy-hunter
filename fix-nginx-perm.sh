#!/bin/bash

# Ensure script runs as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Set www-data user for subsequent commands
USER="www-data"

# Get the absolute path of the current script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

# Load .env file
if [ -f "$SCRIPT_DIR/.env" ]; then
    source "$SCRIPT_DIR/.env"
fi

# Add custom paths to PATH
export PATH="${SCRIPT_DIR}/node_modules/.bin:${SCRIPT_DIR}/bin:${SCRIPT_DIR}/vendor/bin:$PATH"

# Check if Git is installed
if command -v git >/dev/null 2>&1; then
    # Check if the current directory is a Git repository
    if [ -d "$SCRIPT_DIR/.git" ] || git -C "$SCRIPT_DIR" rev-parse --git-dir >/dev/null 2>&1; then
        echo "Current directory is a Git repository. Updating submodules..."
        bash "$SCRIPT_DIR/bin/submodule-update"
    else
        echo "Current directory is not a Git repository."
    fi
else
    echo "Git is not installed. Please install Git to proceed."
fi

# Array of files to remove
lock_files=("$SCRIPT_DIR/proxyWorking.lock" "$SCRIPT_DIR/proxyChecker.lock")

# Loop through the array to remove lock files
for file in "${lock_files[@]}"; do
    if [ -e "$file" ]; then
        rm "$file"
        echo "Removed $file"
    else
        echo "$file does not exist"
    fi
done

# Set permissions on files and directories
chmod 777 "$SCRIPT_DIR"/*.txt
chmod 755 "$SCRIPT_DIR"/*.html "$SCRIPT_DIR"/*.js
chmod 755 "$SCRIPT_DIR"/*.css
chmod 755 "$SCRIPT_DIR"/js/*.js
chmod 777 "$SCRIPT_DIR"/config
chmod 755 "$SCRIPT_DIR"/config/*
chmod 777 "$SCRIPT_DIR"/tmp "$SCRIPT_DIR"/.cache "$SCRIPT_DIR"/data
chmod 644 "$SCRIPT_DIR"/data/*.php
chmod 644 "$SCRIPT_DIR"/*.php
chmod 644 "$SCRIPT_DIR"/.env
chmod 755 "$SCRIPT_DIR"/*.sh

# Create necessary directories and index.html files
mkdir -p "$SCRIPT_DIR/tmp/cookies"
touch "$SCRIPT_DIR/tmp/cookies/index.html"
touch "$SCRIPT_DIR/tmp/index.html"
mkdir -p "$SCRIPT_DIR/config"
touch "$SCRIPT_DIR/config/index.html"
mkdir -p "$SCRIPT_DIR/.cache"
touch "$SCRIPT_DIR/.cache/index.html"

# Additional permissions for specific directories if they exist
if [ -d "$SCRIPT_DIR/assets/proxies" ]; then
    chmod 777 "$SCRIPT_DIR/assets/proxies"
    chmod 755 "$SCRIPT_DIR/assets/proxies"/*
    touch "$SCRIPT_DIR/assets/proxies/index.html"
fi
if [ -d "$SCRIPT_DIR/packages" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/packages"
    chown -R "$USER":"$USER" "$SCRIPT_DIR/packages"/*
fi
if [ -d "$SCRIPT_DIR/public" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/public"
    chown -R "$USER":"$USER" "$SCRIPT_DIR/public"/*
fi

# Allow composer and indexing proxies to work
chown -R "$USER":"$USER" "$SCRIPT_DIR"/*.php "$SCRIPT_DIR"/*.phar

chown -R "$USER":"$USER" "$SCRIPT_DIR/bin"
chmod 755 "$SCRIPT_DIR/bin"/*

echo "Permission sets successful"

OUTPUT_FILE="$SCRIPT_DIR/proxyChecker.txt"
COMPOSER_LOCK="$SCRIPT_DIR/composer.lock"
COMPOSER_PHAR="$SCRIPT_DIR/composer.phar"

# Install or update composer packages
if [ ! -f "$COMPOSER_LOCK" ]; then
    su -s /bin/sh -c "php $COMPOSER_PHAR install --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" "$USER"
else
    su -s /bin/sh -c "php $COMPOSER_PHAR update --no-dev --no-interaction >> $OUTPUT_FILE 2>&1" "$USER"
fi

# Function to check if a process is running and start it if not, or force run it
run_php_if_not_running() {
    local script_name="$1"
    local script_args="$2"
    local force="${3:-false}" # Default to false if not provided

    if pgrep -f "$script_name" >/dev/null; then
        if [ "$force" = "true" ]; then
            echo "$script_name is running but will be forcefully restarted."
            pkill -f "$script_name"
            su -s /bin/sh -c "php $SCRIPT_DIR/$script_name $script_args >> $OUTPUT_FILE 2>&1 &" "$USER"
        else
            echo "$script_name is still running."
        fi
    else
        su -s /bin/sh -c "php $SCRIPT_DIR/$script_name $script_args >> $OUTPUT_FILE 2>&1 &" "$USER"
    fi
}

# Validate and run scripts if not already running
run_php_if_not_running "proxies-all.php" "--admin=true"
run_php_if_not_running "filterPortsDuplicate.php" "--admin=true --delete=true"
run_php_if_not_running "filterPorts.php" "--admin=true"
run_php_if_not_running "proxyChecker.php" "--admin=true --max=1000" "true"

# Set permissions for vendor directory
chmod 777 "$SCRIPT_DIR/vendor"
touch "$SCRIPT_DIR/vendor/index.html"

echo "Composer installed"

# Fix ownership for various directories and file types
chown -R "$USER":"$USER" "$SCRIPT_DIR"/*.php "$SCRIPT_DIR"/*.txt "$SCRIPT_DIR"/*.json "$SCRIPT_DIR"/*.js "$SCRIPT_DIR"/*.html "$SCRIPT_DIR"/src "$SCRIPT_DIR"/data "$SCRIPT_DIR"/tmp "$SCRIPT_DIR"/vendor "$SCRIPT_DIR"/assets
chown -R "$USER":"$USER" "$SCRIPT_DIR"/.cache "$SCRIPT_DIR"/config "$SCRIPT_DIR"/*.css "$SCRIPT_DIR"/*.lock "$SCRIPT_DIR"/js "$SCRIPT_DIR"/.htaccess "$SCRIPT_DIR"/.env

echo "Ownership fixed"

# Enable Git LFS and track large files
git -C "$SCRIPT_DIR" lfs install
git -C "$SCRIPT_DIR" lfs track "*.rar"

echo "Large files tracked"

# Restart services
touch "$SCRIPT_DIR/assets/index.html"
touch "$SCRIPT_DIR/assets/systemctl/index.html"
sudo chmod +x "$SCRIPT_DIR/assets/systemctl"/*

# Function to copy file if both source and destination exist
copy_if_both_exist() {
    local source_file="$1"
    local dest_file="$2"

    if [ -f "$source_file" ] && [ -f "$dest_file" ]; then
        cp "$source_file" "$dest_file"
        echo "Copied $source_file to $dest_file"
    else
        echo "Skipping. Source file $source_file or destination file $dest_file does not exist."
    fi
}

# Copy gunicorn.service to /etc/systemd/system/gunicorn.service
copy_if_both_exist "$SCRIPT_DIR/assets/systemctl/gunicorn.service" "/etc/systemd/system/gunicorn.service"

# Copy .htaccess_nginx.conf to /etc/nginx/sites-available/default
copy_if_both_exist "$SCRIPT_DIR/.htaccess_nginx.conf" "/etc/nginx/sites-available/default"

# Install python requirements
sudo -u "$USER" -H bash -c "python3.11 -m venv $SCRIPT_DIR/venv"
sudo -u "$USER" -H bash -c "source $SCRIPT_DIR/venv/bin/activate && python3 $SCRIPT_DIR/requirements_install.py"

if [ -d "$SCRIPT_DIR/venv" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/venv"
    chmod 755 "$SCRIPT_DIR/venv/bin"/*
fi

if [ -d "$SCRIPT_DIR/django_backend" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/django_backend"
    chown -R "$USER":"$USER" "$SCRIPT_DIR/django_backend"/*
    touch "$SCRIPT_DIR/django_backend/index.html"
fi

if [ -d "$SCRIPT_DIR/xl" ]; then
    chown -R "$USER":"$USER" "$SCRIPT_DIR/xl"
    chown -R "$USER":"$USER" "$SCRIPT_DIR/xl"/*
    touch "$SCRIPT_DIR/xl/index.html"
fi

# reload django
function run_as_user_in_venv() {
    local COMMAND=$1
    sudo -u "$USER" -H bash -c "source $SCRIPT_DIR/venv/bin/activate && $COMMAND"
}

# run_as_user_in_venv "python $SCRIPT_DIR/manage.py makemigrations"
run_as_user_in_venv "python $SCRIPT_DIR/manage.py migrate"

# reload daemon
sudo systemctl daemon-reload

# Check and restart PHP-FPM if installed
# Array of PHP versions to check
php_versions=("7.2" "7.4" "8.0")

# Iterate over each PHP version
for version in "${php_versions[@]}"; do
    # Check if PHP FPM service is active
    if systemctl is-active --quiet php${version}-fpm; then
        sudo systemctl restart php${version}-fpm
        echo "Restarted PHP ${version} FPM"
    fi
done

# Check and restart Nginx if installed
if systemctl is-active --quiet nginx; then
    sudo nginx -t
    sudo systemctl restart nginx
    echo "Restarted Nginx"
fi

# Check and restart Gunicorn if installed
if systemctl is-active --quiet gunicorn; then
    sudo systemctl restart gunicorn
    echo "Restarted Gunicorn"
fi

# Check and restart Spring Boot if installed
if systemctl is-active --quiet spring; then
    sudo systemctl restart spring
    echo "Restarted Spring Boot"
fi
