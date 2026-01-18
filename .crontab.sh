#!/bin/bash

# Set current directory as working directory
# Use $0 instead of ${BASH_SOURCE[0]} for better compatibility
CWD="$(cd "$(dirname "$0")" >/dev/null 2>&1 && pwd)"

# Change to the directory stored in CWD
cd "$CWD"

# Set www-data user for subsequent commands
USER="www-data"

# Detect python virtual bin by operating system
if [ "$(uname -s)" = "Darwin" ] || [ "$(uname -s)" = "Linux" ]; then
    # Unix-based systems (Linux, macOS)
    VENV_BIN="$CWD/venv/bin"
else
    # Assume Windows
    VENV_BIN="$CWD/venv/Scripts"
fi

# Ensure essential system bin dirs are first in PATH, then project bins, then existing PATH
# This guarantees commands like `sqlite3` and `mysqldump` are found when run from cron
export PATH="/usr/local/bin:/usr/bin:/usr/local/sbin:/usr/sbin:/sbin:/bin:$CWD/bin:$CWD/node_modules/.bin:$CWD/vendor/bin:$VENV_BIN${PATH:+:$PATH}"

# Load .env file
if [ -f "$CWD/.env" ]; then
    . "$CWD/.env"  # Use . instead of source for sh compatibility
fi

r_cmd() {
    COMMAND_RUNNER=$1
    FILE_PATH=$2
    COMMAND_ARGS=${@:3}
    is_running=false

    # check script is running
    if [ "$OSTYPE" = "cygwin" ] || [ "$OSTYPE" = "msys" ]; then
        # Use ps for Windows (Cygwin)
        if ps aux | grep -v grep | grep "$COMMAND_RUNNER $CWD/$FILE_PATH" >/dev/null; then
            is_running=true
        fi
        wmic process where "name='$COMMAND_RUNNER.exe'" get commandline /format:list | grep -i "$FILE_PATH"
        if wmic process where "name='$COMMAND_RUNNER.exe'" get commandline /format:list | grep -i "$FILE_PATH" >/dev/null; then
            is_running=true
        fi
    else
        # Use pgrep for Unix-like systems
        if pgrep -f "$COMMAND_RUNNER $CWD/$FILE_PATH" >/dev/null; then
            is_running=true
        fi
    fi

    if $is_running; then
        echo "$CWD/$FILE_PATH is still running."
    else
        if [ "$(uname)" = "Linux" ] && [ -x "$(command -v nginx)" ]; then
            USER="www-data"
            COMMAND=". $CWD/venv/bin/activate && $COMMAND_RUNNER $FILE_PATH $COMMAND_ARGS"
            echo "Executing as $USER: $COMMAND"
            sudo -u "$USER" -H bash -c "$COMMAND"
        else
            COMMAND=". $CWD/venv/Scripts/activate && $COMMAND_RUNNER $FILE_PATH $COMMAND_ARGS"
            echo "Executing: $COMMAND"
            eval "$COMMAND"
        fi
    fi
}

# Example usage:
# r_cmd "python" "manage.py" "runserver"
# r_cmd "php" "path/to/script.php" "arg1 arg2"

# r_cmd "python" "artisan/filterPortsDuplicate.py" "--max=10"
# r_cmd "python" "proxyCheckerReal.py" "--max=10"

# Helper function to check if it's time to run a job by timestamp file content comparison
should_run_job() {
    local file_path=$1
    local interval_hours=$2

    current_time=$(date +%s)                       # Current timestamp in seconds
    interval_seconds=$(awk -v h="$interval_hours" 'BEGIN {printf "%d\n", h * 60 * 60}')

    # Check if timestamp file exists
    if [ -f "$file_path" ]; then
        last_fetch=$(cat "$file_path")
        elapsed_time=$((current_time - last_fetch))

        if [ $elapsed_time -ge $interval_seconds ]; then
            # Update the timestamp file with the current time
            echo "$current_time" >"$file_path"
            return 0 # True, it's time to run the job
        else
            return 1 # False, it's not time to run the job
        fi
    else
        # Create the file and update it with the current time
        echo "$current_time" >"$file_path"
        return 0 # True, file not found, so it's time to run the job
    fi
}

mkdir -p tmp/crontab
mkdir -p tmp/logs/crontab

# Logging function to capture stdout/stderr with timestamp - runs in background
log_command() {
    local log_file=$1
    shift  # Remove first argument, rest is the command

    # Auto create log directory if it doesn't exist
    local log_dir=$(dirname "$log_file")
    mkdir -p "$log_dir"

    # Run command in background with logging
    {
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running: $@"
        "$@"
        exit_code=$?
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Exit code: $exit_code"
        echo ""
        echo "===================="
        echo ""
    } > "$log_file" 2>&1 &
}

# run every 5 minutes
if should_run_job "tmp/crontab/5-m" 0.0833; then
    echo "Running 5 minutes job."
    "$CWD/bin/py" "$CWD/artisan/proxy-classifier-lookup.py" --max=1000 > "tmp/logs/crontab/proxy-classifier-lookup.log" 2>&1
    "$CWD/bin/py" "$CWD/artisan/filter_duplicate_ips.py" --limit=1000 > "tmp/logs/crontab/filter-duplicate-ips.log" 2>&1
else
    echo "Skipping 5 minutes job."
fi

# run every 30 minutes
if should_run_job "tmp/crontab/30-m" 0.5; then
    echo "Running 30 minutes job."
    # r_cmd "python" "artisan/filterPortsDuplicate.py" "--max=50"
    # r_cmd "python" "proxyCheckerReal.py" "--max=50"
    # log_command "tmp/logs/crontab/proxy-collector.log" php artisan/proxyCollector.php || true
    # log_command "tmp/logs/crontab/proxy-collector2.log" php artisan/proxyCollector2.php || true
    # log_command "tmp/logs/crontab/check-old-proxy.log" php php_backend/check-old-proxy.php
    # Run geoIp script to resolve missing geo information for proxies
    # log_command "tmp/logs/crontab/geoip.log" php geoIp.php
    log_command "tmp/logs/crontab/proxyCollector2.log" "$CWD/bin/py" artisan/proxyCollector2.py --batch-size=500 --shuffle
    log_command "tmp/logs/crontab/proxyCollector.log" "$CWD/bin/py" artisan/proxyCollector.py --batch-size=500 --shuffle
else
    echo "Skipping 30 minutes job."
fi

# run every hour
if should_run_job "tmp/crontab/1-h" 1; then
    # log_command "tmp/logs/crontab/djm_check_proxies.log" djm check_proxies --max=100
    # log_command "tmp/logs/crontab/djm_filter_dups.log" djm filter_dups --max=100
    log_command "tmp/logs/crontab/filter-ports.log" php artisan/filterPorts.php
    log_command "tmp/logs/crontab/filter-ports-background.log" php artisan/filterPortsDuplicate.php
    # log_command "tmp/logs/crontab/check-http-proxy.log" php php_backend/check-http-proxy.php
    # log_command "tmp/logs/crontab/check-https-proxy.log" php php_backend/check-https-proxy.php
    echo "Running 1 hour job."
else
    echo "Skipping 1 hour job."
fi

# run every 3 hours
if should_run_job "tmp/crontab/3-h" 3; then
    echo "Running 3 hours job."
    # log_command "tmp/logs/crontab/check-proxy-parallel.log" bash "$CWD/bin/check-proxy-parallel"
    log_command "tmp/logs/crontab/proxy_checker_httpx.log" "$CWD/bin/py" "$CWD/artisan/proxy_checker_httpx.py"
else
    echo "Skipping 3 hours job."
fi

# run every 4 hours
if should_run_job "tmp/crontab/4-h" 4; then
    echo "Running 4 hours job."
    # run proxy fetcher in background
    python "$CWD/proxyFetcher.py" > "tmp/logs/crontab/proxy-fetcher.log" 2>&1 &
else
    echo "Skipping 4 hours job."
fi

# run every 6 hours
if should_run_job "tmp/crontab/6-h" 6; then
    # log_command "tmp/logs/crontab/djm_sync_proxies.log" djm sync_proxies
    echo "Running 6 hours job."
else
    echo "Skipping 6 hours job."
fi

# run every 12 hours
if should_run_job "tmp/crontab/12-h" 12; then
    echo "Running 12 hours job."
    # Truncate WAL files for SQLite databases
    if [ -f "$CWD/tmp/database.sqlite-wal" ]; then
        echo "Checkpointing and truncating WAL file..."
        sqlite3 "$CWD/tmp/database.sqlite" "PRAGMA wal_checkpoint(TRUNCATE);"
        echo "$CWD/tmp/database.sqlite WAL file truncated."
    fi
    if [ -f "$CWD/src/database.sqlite-wal" ]; then
        echo "Checkpointing and truncating WAL file..."
        sqlite3 "$CWD/src/database.sqlite" "PRAGMA wal_checkpoint(TRUNCATE);"
        echo "$CWD/src/database.sqlite WAL file truncated."
    fi
else
    echo "Skipping 12 hours job."
fi

# run every 24 hours
if should_run_job "tmp/crontab/24-h" 24; then
    echo "Running 24 hours job."
    # Backup database
    log_command "tmp/logs/crontab/backup-db.log" bash -e "$CWD/bin/backup-db"
    # Run php cleanup script
    log_command "tmp/logs/crontab/php-cleaner.log" php "$CWD/artisan/cleaner.php"
    # Remove old backups older than 7 days
    log_command "tmp/logs/crontab/cleanup-backups.log" find "$CWD/backups" -type f -name "*.sql" -mtime +7 -exec rm -f {} \;
    echo "Old backups removed, keeping only the last 7 days."
    # Remove old log files older than 30 days
    log_command "tmp/logs/crontab/cleanup-logs.log" find "$CWD/tmp/logs" -type f -name "*.log" -mtime +30 -exec rm -f {} \;
    echo "Old log files removed, keeping only the last 30 days."
else
    echo "Skipping 24 hours job."
fi

# run every 3 days
if should_run_job "tmp/crontab/72-h" 72; then
    echo "Running 72 hours job."
    # Run backups cleanup script every 3 days
    log_command "tmp/logs/crontab/cleanup-backups-3d.log" "$CWD/bin/py" "$CWD/src/dev/backup-cleaner.py"
else
    echo "Skipping 72 hours job."
fi
