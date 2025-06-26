#!/bin/bash

set -eu  # Exit on error and undefined variable

clear

# ─── Constants ────────────────────────────────────────────────────────────────

OS="$(uname -s)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV_DIR="$SCRIPT_DIR/venv"
VENV_BIN_LINUX="$VENV_DIR/bin"
VENV_BIN_WINDOWS="$VENV_DIR/Scripts"
REQUIREMENTS_SCRIPT="$SCRIPT_DIR/requirements_install.py"
COMPOSER_LOCK="$SCRIPT_DIR/composer.lock"
COMPOSER_PHAR="$SCRIPT_DIR/composer.phar"
CACHE_DIR="$SCRIPT_DIR/tmp/.cache/pip"
USER="www-data"

# ─── Functions ────────────────────────────────────────────────────────────────

require_root_linux() {
  if [ "$OS" = "Linux" ] && [ "$EUID" -ne 0 ]; then
    echo "Please run this script as root"
    exit 1
  fi
}

ensure_env_loaded() {
  [ -f "$SCRIPT_DIR/.env" ] && source "$SCRIPT_DIR/.env"
}

set_permissions() {
  local target="$1"
  if [ "$OS" = "Linux" ] && [ -d "$target" ]; then
    sudo chown -R "$USER:$USER" "$target"
    echo "Ownership updated for $target"
  fi
}

create_virtualenv() {
  if [ -d "$VENV_DIR" ]; then
    echo "Virtual environment already exists."
    return
  fi

  echo "Creating virtual environment..."
  local python_bin
  python_bin=$(command -v python3 || command -v python || { echo "Python not found"; exit 1; })

  if [[ "$OS" =~ Darwin|Linux ]]; then
    sudo -u "$USER" -H "$python_bin" -m venv "$VENV_DIR"
  else
    "$python_bin" -m venv "$VENV_DIR"
  fi
}

activate_virtualenv() {
  if [[ "$OS" =~ Darwin|Linux ]]; then
    source "$VENV_BIN_LINUX/activate"
    PYTHON_BINARY="$VENV_BIN_LINUX/python"
  else
    source "$VENV_BIN_WINDOWS/activate"
    PYTHON_BINARY="$VENV_BIN_WINDOWS/python.exe"
  fi
}

prepare_pip_cache() {
  mkdir -p "$CACHE_DIR"
  chmod -R 777 "$CACHE_DIR"
}

update_path() {
  export PATH="${PATH:-}":"$SCRIPT_DIR/bin:$SCRIPT_DIR/node_modules/.bin:$SCRIPT_DIR/vendor/bin:$VENV_BIN_LINUX"
}

upgrade_python_tools() {
  echo "Upgrading pip, setuptools, and wheel..."
  if [ "$OS" = "Linux" ]; then
    sudo -u "$USER" -H "$PYTHON_BINARY" -m ensurepip
    sudo -u "$USER" -H "$PYTHON_BINARY" -m pip install --upgrade pip setuptools wheel --cache-dir "$CACHE_DIR"
  else
    "$PYTHON_BINARY" -m ensurepip --upgrade
    "$PYTHON_BINARY" -m pip install --upgrade pip setuptools wheel --cache-dir "$CACHE_DIR"
  fi
}

install_python_requirements() {
  echo "Installing Python requirements..."
  if [ "$OS" = "Linux" ]; then
    sudo -u "$USER" -H bash -c "source $VENV_DIR/bin/activate && $PYTHON_BINARY $REQUIREMENTS_SCRIPT --generate"
    sudo -u "$USER" -H "$PYTHON_BINARY" -m pip install -r "$SCRIPT_DIR/requirements.txt" --cache-dir "$CACHE_DIR"
  else
    "$PYTHON_BINARY" "$REQUIREMENTS_SCRIPT" --generate
    "$PYTHON_BINARY" -m pip install -r "$SCRIPT_DIR/requirements.txt" --cache-dir "$CACHE_DIR"
  fi
}

install_composer_dependencies() {
  if [ -f "$COMPOSER_PHAR" ]; then
    echo "Installing PHP dependencies..."
    if [ "$OS" = "Linux" ]; then
      sudo -u "$USER" -H php "$COMPOSER_PHAR" install --no-dev --no-interaction
    else
      php "$COMPOSER_PHAR" install --no-dev --no-interaction
    fi
  fi
}

install_node_dependencies() {
  echo "Installing Node.js dependencies..."
  if [ -d "$HOME/.nvm" ]; then
    export NVM_DIR="$HOME/.nvm"
  elif [ -d "/usr/local/nvm" ]; then
    export NVM_DIR="/usr/local/nvm"
  elif [[ "$OSTYPE" =~ msys|cygwin|mingw ]]; then
    echo "Windows environment detected. Skipping NVM load."
    return
  else
    echo "NVM not found."
    exit 1
  fi

  if [ "$OS" = "Linux" ] && [ -s "$NVM_DIR/nvm.sh" ]; then
    . "$NVM_DIR/nvm.sh"
  fi

  corepack enable yarn
  yarn install
}

apply_cron_jobs() {
  if [ "$OS" = "Linux" ]; then
    sudo crontab -u "$USER" "$SCRIPT_DIR/.crontab.txt"
  fi
}

finalize_setup() {
  rollup -c
  [ "$OS" = "Linux" ] && bash -e "$SCRIPT_DIR/bin/fix-perm"
  git config merge.resolve_hash.driver "node bin/create-file-hashes.cjs %O %A %B"
}

# ─── Main Script Execution ────────────────────────────────────────────────────

echo "Script directory: $SCRIPT_DIR"

require_root_linux
ensure_env_loaded

set_permissions "/var/www/html"
set_permissions "/var/www/.cache/pip"

create_virtualenv
activate_virtualenv
prepare_pip_cache
update_path

echo "Python binary location: $PYTHON_BINARY"

upgrade_python_tools
install_python_requirements
install_composer_dependencies
install_node_dependencies
apply_cron_jobs
finalize_setup

echo "Setup completed successfully."
