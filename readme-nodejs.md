## Requirements

- NodeJS v20

> For linux user, NodeJS v20 only compatible for Ubuntu 20.x (VPS)

### Install required libraries

```bash
sudo apt update -y
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

    or automatically using shell terminal:

    ```bash
    sudo tee /etc/profile.d/nvm.sh > /dev/null <<'EOF'
    export NVM_DIR="/usr/local/nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
    EOF
    ```

5.  Set permissions `chmod 755 /etc/profile.d/nvm.sh`

6.  Run `/etc/profile.d/nvm.sh` or load within current shell  `. /etc/profile.d/nvm.sh` or `source /etc/profile.d/nvm.sh`

7.  Install node: `nvm install v20`

8. Compile & symlink Node from `nvm`

   ```bash
   NODE_BIN_DIR="$(dirname $(nvm which 20))"

   sudo ln -sf "$NODE_BIN_DIR/node" /usr/local/bin/node
   sudo ln -sf "$NODE_BIN_DIR/npm" /usr/local/bin/npm
   sudo ln -sf "$NODE_BIN_DIR/npx" /usr/local/bin/npx
   sudo ln -sf "$NODE_BIN_DIR/corepack" /usr/local/bin/corepack
   ```

   > Then all users just run `node` / `npm` without caring about `nvm`.

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

## Install NodeJS with NVM for Windows

NVM for Windows is a separate project from the Linux/Mac version. It allows you to easily manage and switch between Node.js versions on Windows.

### Download NVM for Windows
Go to the [NVM for Windows releases page](https://github.com/coreybutler/nvm-windows/releases) and download the latest `nvm-setup.zip` or `nvm-setup.exe` installer.

### Install NVM
Run the installer and follow the prompts. Accept the default installation paths unless you have a specific need to change them.

### Open a new Command Prompt
After installation, open a new `cmd.exe` window to ensure NVM is available in your PATH.

### Install Node.js v20
```bash
nvm install 20
nvm use 20
node -v   # Should print a version like v20.x.x
npm -v    # Should print the npm version
```

### (Optional) Install Yarn
```bash
npm install -g yarn
yarn -v
```

> For more details, see the [NVM for Windows documentation](https://github.com/coreybutler/nvm-windows#installation--upgrades).

## Access vite dev server from same LAN/Router

Run below command after run `yarn dev:vite` on another terminal/cmd

### Random domain

```bash
npx cloudflared tunnel --url https://localhost:5173 --no-tls-verify
```

### Custom domain

> To get list of your tunnels, use `cloudflared tunnel list`

Create **One dash zero trust** token

```bash
npx cloudflared service install <YOUR_CLOUDFLARED_TOKEN>
npx cloudflared tunnel route dns <YOUR_TUNNEL_NAME> your.domain.com
cloudflared tunnel --config cloudflared.config.yaml run <YOUR_TUNNEL_NAME> # dont forget to edit yaml file
# or
cloudflared tunnel run --token <YOUR_CLOUDFLARED_TOKEN>
```

when confused, try delete and create forcibly

```bash
cloudflared tunnel delete --force mytunnel
cloudflared tunnel list # No tunnels were found for the given filter flags. You can use 'cloudflared tunnel create' to create a tunnel.
cloudflared tunnel create mytunnel # Tunnel credentials written to C:\Users\<name>\.cloudflared\<tunnel-id>.json. cloudflared chose this file based on where your origin certificate was found. Keep this file secret. To revoke these credentials, delete the tunnel.
cloudflared tunnel route dns mytunnel your.domain.com # when fail tunnel, try replace manual DNS CNAME for you domain with format <TUNNEL-ID>.cfargotunnel.com

# now edit file cloudflared.config.yaml with your credential information
# then run

cloudflared tunnel --config cloudflared.config.yaml run mytunnel
```
