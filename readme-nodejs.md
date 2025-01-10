## Requirements

- NodeJS v20

> For linux user, NodeJS v20 only compatible for Ubuntu 20.x (VPS)

NodeJS installation steps https://nodejs.org/en/download/current (v23)

```bash
sudo apt install -y ca-certificates curl gnupg
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
# Load NVM
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"  # This loads nvm
nvm --version
```