#!/bin/bash

# Get the directory of the current script
SCRIPT_DIR=$(realpath "$(dirname "$0")")

# Set current working directory to the parent directory of the grandparent
CWD=$(realpath "$(dirname "$(dirname "$SCRIPT_DIR")")")

echo "Current directory: $CWD"

# Load .env file if it exists
if [ -f "$CWD/.env" ]; then
  echo "Loading environment variables from $CWD/.env"
  # Use `set -a` and `set +a` to ensure exported variables are available
  set -a
  source "$CWD/.env"
  set +a
else
  echo "No .env file found in $CWD"
fi

export NVM_DIR="$HOME/.nvm"

# Check if NVM is installed and load it
if [ -d "$NVM_DIR" ] && [ -s "$NVM_DIR/nvm.sh" ]; then
  # Load NVM into the shell session
  . "$NVM_DIR/nvm.sh"

  # Enable corepack and install yarn
  corepack enable yarn && yarn install
else
  echo "NVM is not installed. Please install NVM first."
  exit 1
fi

# /root/.nvm/versions/node/v20.18.1/bin/node /var/www/html/dist/whatsapp-xl.js
# node /var/www/html/dist/whatsapp-xl.js
# "$CWD/node_modules/.bin/nodemon" --exec "/root/.nvm/versions/node/v20.18.1/bin/node" /var/www/html/whatsapp-xl.js
# nodemon --watch dist/whatsapp-xl.js dist/whatsapp-xl.js
"$CWD/node_modules/.bin/nodemon" --watch "$CWD/dist/whatsapp-xl.js" "$CWD/dist/whatsapp-xl.js"

