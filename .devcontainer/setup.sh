#!/bin/bash

# Configure Git
git config --global user.name "Dimas Lanjaka"
git config --global user.email "dimaslanjaka@gmail.com"

# Check if GitHub CLI is installed
if ! command -v gh &> /dev/null
then
    echo "GitHub CLI (gh) could not be found. Installing..."
    curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
    sudo chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
    sudo apt update
    sudo apt install gh -y
fi

# Check if Git LFS is installed
if ! command -v git-lfs &> /dev/null
then
    echo "Git LFS could not be found. Installing..."
    curl -s https://packagecloud.io/install/repositories/github/git-lfs/script.deb.sh | sudo bash
    sudo apt-get install git-lfs -y
    git lfs install
fi

# Authenticate GitHub CLI
echo $GH_TOKEN | gh auth login --with-token

# Store GitHub credentials
echo -e "protocol=https\nhost=github.com\nusername=dimaslanjaka@gmail.com\npassword=$GH_TOKEN" | git credential-store --file ~/.git-credentials store
