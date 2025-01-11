## Requirements

- NodeJS v20

> For linux user, NodeJS v20 only compatible for Ubuntu 20.x (VPS)

### Install required libraries

```bash
sudo apt install -y ca-certificates fonts-liberation libasound2 libatk-bridge2.0-0 libatk1.0-0 libc6 libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgbm1 libgcc1 libglib2.0-0 libgtk-3-0 libnspr4 libnss3 libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 libxtst6 lsb-release wget xdg-utils
```

### Install NodeJS for all users

1.  Login as root: `sudo -s`

2.  Create destination folder: `mkdir -p /usr/local/nvm`

3.  Install nvm: `curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.33.1/install.sh | NVM_DIR=/usr/local/nvm bash`

4.  Create a file called `nvm.sh` in `/etc/profile.d` with the following contents:

    ```bash
    #!/usr/bin/env bash
    export NVM_DIR="/usr/local/nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
    ```

5.  Set permissions `chmod 755 /etc/profile.d/nvm.sh`

6.  Run `/etc/profile.d/nvm.sh` or load within current shell  `. /etc/profile.d/nvm.sh`

7.  Install node: `nvm install v20`

### Install NodeJS for root user

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

## Install NodeJS for user

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