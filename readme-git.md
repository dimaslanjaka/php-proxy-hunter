# Documentation for git installation

## Install required libraries

```bash
sudo apt update -y
sudo apt-get -y install dh-autoreconf libcurl4-gnutls-dev libexpat1-dev gettext libz-dev libssl-dev build-essential
```

## Install git from system repository

```bash
sudo apt install git git-all git-lfs -y
```

## Install git from source

```bash
cd /tmp
wget https://github.com/git/git/archive/refs/tags/v2.47.1.tar.gz -O git-2.47.1.tar.gz
tar -zxf git-2.47.1.tar.gz
cd git-2.47.1
make prefix=/usr/local all
sudo make prefix=/usr/local install
```

## Fix permissions

```bash
# Disable tracking of file permission changes in Git
# This prevents Git from considering changes to file permissions as modifications
git config core.fileMode false

# Change the ownership of all files in /var/www/html to the www-data user and group
# This allows the web server (often running as www-data) to manage the files
sudo chown -R www-data:www-data /var/www/html

# Grant group read and write access to all files in /var/www/html
# This allows members of the 'www-data' group to edit the files
sudo chmod -R g+rw /var/www/html

# Configure the Git repository to allow shared access by the group
# This enables collaboration between users in the same group on the Git repository
git config core.sharedRepository group
```
