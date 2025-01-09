# Documentation for git installation

## Install required libraries

```bash
sudo apt update -y
sudo apt-get -y install dh-autoreconf libcurl4-gnutls-dev libexpat1-dev gettext libz-dev libssl-dev build-essential
```

## Install git from system repository

```bash
sudo apt install git git-all -y
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