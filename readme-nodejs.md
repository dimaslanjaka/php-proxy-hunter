## Requirements

- NodeJS v20

> For linux user, NodeJS v20 only compatible for Ubuntu 20.x (VPS)

```bash
# Install requirements
sudo apt install -y ca-certificates curl gnupg

# Download and install nvm:
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash

# Download and install Node.js:
nvm install 20

# Verify the Node.js version:
node -v # Should print "v20.18.1".
nvm current # Should print "v20.18.1".

# Download and install Yarn:
corepack enable yarn

# Verify Yarn version:
yarn -v
```

## Setup user for nodejs

```bash
# Create user
sudo usermod -s /bin/bash www-data
# Switch user
sudo -u www-data -i
# Add service support
sudo usermod -s /usr/sbin/nologin www-data
```

Change to root user, make sure ownership of /var/www/html to www-data

```bash
sudo chown -R www-data:www-data /var/www/html
```

Switch to user www-data, then install NVM

```bash
# Install NVM
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
# Download and install NodeJS
nvm install 20
# Download and install yarn
corepack enable yarn
# Load NVM
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
nvm --version
```

Build project environment

```bash
rollup -c && rollup -c # build twice ONLY for first run
rollup -c rollup.php.js
```