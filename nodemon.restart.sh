#!/bin/bash

# run with
# nodemon --config nodemon.config.json --exec [your command]
# eg:
# nodemon --config nodemon.config.json --exec build-project

LOCKFILE="tmp/runners/nodemon.lock"

# Create the directory for the lock file if it doesn't exist
mkdir -p "$(dirname "$LOCKFILE")"

# Function to remove the lock file on exit
cleanup() {
    rm -f "$LOCKFILE"
}

# Check if the lock file exists
if [ -f "$LOCKFILE" ]; then
    echo "Script is already running."
else
    touch "$LOCKFILE"
    trap cleanup EXIT
    clear
    gulp prepare
    if [ -f "$LOCKFILE" ]; then
        rm -f "$LOCKFILE"
    fi
    # node --harmony -r ts-node/register node_browser/express-server.js
fi
