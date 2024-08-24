#!/bin/bash

# Ensure script runs as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
fi

# Set www-data user for subsequent commands
USER="www-data"

# Get the absolute path of the current script directory
CWD="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

# Load .env file
if [ -f "$CWD/.env" ]; then
    source "$CWD/.env"
fi

# Detect python virtual bin by operating system
if [ "$(uname -s)" = "Darwin" ] || [ "$(uname -s)" = "Linux" ]; then
    # Unix-based systems (Linux, macOS)
    VENV_BIN="$CWD/venv/bin"
else
    # Assume Windows
    VENV_BIN="$CWD/venv/Scripts"
fi

# Check if PATH is set
if [ -z "$PATH" ]; then
    export PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN
else
    export PATH=$PATH:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN
fi

# Check if Git is installed
if command -v git >/dev/null 2>&1; then
    # Check if the current directory is a Git repository
    if [ -d "$CWD/.git" ] || git -C "$CWD" rev-parse --git-dir >/dev/null 2>&1; then
        echo "Current directory is a Git repository. Updating submodules..."
        bash "$CWD/bin/submodule-update"
    else
        echo "Current directory is not a Git repository."
    fi
else
    echo "Git is not installed. Please install Git to proceed."
fi

# Set permissions on files and directories
chown -R "$USER":"$USER" *
chmod 777 "$CWD"/*.txt
chmod 755 "$CWD"/*.html "$CWD"/*.js
chmod 755 "$CWD"/*.css
chmod 755 "$CWD"/js/*.js "$CWD"/userscripts/*.js
chmod 777 "$CWD"/config
chmod 755 "$CWD"/config/*
chmod 777 "$CWD"/tmp "$CWD"/.cache "$CWD"/data
chmod 644 "$CWD"/.env
chmod 755 "$CWD"/*.sh
chown -R "$USER":"$USER" "$CWD/bin"
chmod 755 "$CWD/bin"/*

if [ -d "$CWD/assets/proxies" ]; then
    chmod 777 "$CWD/assets/proxies"
    chmod 755 "$CWD/assets/proxies"/*
    touch "$CWD/assets/proxies/index.html"
fi
if [ -d "$CWD/packages" ]; then
    chown -R "$USER":"$USER" "$CWD/packages"
    chown -R "$USER":"$USER" "$CWD/packages"/*
fi
if [ -d "$CWD/public" ]; then
    chown -R "$USER":"$USER" "$CWD/public"
    chown -R "$USER":"$USER" "$CWD/public"/*
fi
if [ -d "$CWD/django_backend" ]; then
    chown -R "$USER":"$USER" "$CWD/django_backend"
    chown -R "$USER":"$USER" "$CWD/django_backend"/*
    touch "$CWD/django_backend/index.html"
fi
if [ -d "$CWD/xl" ]; then
    chown -R "$USER":"$USER" "$CWD/xl"
    chown -R "$USER":"$USER" "$CWD/xl"/*
    touch "$CWD/xl/index.html"
fi

# Install python requirements
sudo -u "$USER" -H bash -c "python3.11 -m venv $CWD/venv"
sudo -u "$USER" -H bash -c "source $CWD/venv/bin/activate && python3 $CWD/requirements_install.py"
sudo -u "$USER" -H bash -c "source $CWD/venv/bin/activate && pip3 install --upgrade pip setuptools wheel"

if [ -d "$CWD/venv" ]; then
    chown -R "$USER":"$USER" "$CWD/venv"
    chmod 755 "$CWD/venv/bin"/*
fi

# Copy gunicorn.service to /etc/systemd/system/gunicorn.service
cp -r "$CWD/assets/systemctl/gunicorn.service" "/etc/systemd/system/gunicorn.service"
cp -r "$CWD/assets/systemctl/django-huey.service" "/etc/systemd/system/huey.service"

# Fix permission for gunicorn services
chmod 755 "$CWD/assets/systemctl"
chown root:root "$CWD/assets/systemctl/start_gunicorn.sh"
chmod +x "$CWD/assets/systemctl/start_gunicorn.sh"

# Fix permission for additional files
chmod 755 "$CWD/data"
chmod 755 "$CWD/userscripts"
chmod 755 "$CWD/tmp/logs"
chmod 755 "$CWD/tmp/requests_cache"
chmod 755 "$CWD/tmp/cookies"
chown www-data:www-data "$CWD/tmp"
chown www-data:www-data "$CWD/userscripts"
chown www-data:www-data "$CWD/data"

function run_as_user_in_venv() {
    local COMMAND=$1
    sudo -u "$USER" -H bash -c "source $CWD/venv/bin/activate && $COMMAND"
}

# migrate database (when changed)
run_as_user_in_venv "python $CWD/manage.py makemigrations"
run_as_user_in_venv "python $CWD/manage.py migrate"
# collect static files (to sync with nginx config)
run_as_user_in_venv "python $CWD/manage.py collectstatic --noinput"
# clear django caches (from django_backend/apps/core/management/commands/clear_cache.py)
run_as_user_in_venv "python $CWD/manage.py clear_cache"
# sync proxies between php and django python databases
run_as_user_in_venv "python $CWD/manage.py sync_proxies"
# fix invalid proxies
run_as_user_in_venv "python $CWD/manage.py fix_proxies"

# Reload daemon
sudo systemctl daemon-reload

# Reload django services
systemctl restart gunicorn
echo "Gunicorn service restarted"
systemctl restart huey
echo "Huey service restarted"
