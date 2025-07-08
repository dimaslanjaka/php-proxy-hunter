import os
import sys

sys.path.insert(
    0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../"))
)
from src.vps.vps_connector import VPSConnector


def pull_latest_code(vps: VPSConnector):
    """Pull the latest code from the git repository on the VPS."""
    return vps.run_command_live("git pull", "/var/www/html")


def composer_update(vps: VPSConnector):
    """Run 'composer update' in the project directory on the VPS."""
    return vps.run_command_live("php composer.phar update", "/var/www/html")


def yarn_install(vps: VPSConnector):
    """Run 'yarn install' in the project directory on the VPS, sourcing .bashrc for environment setup."""
    # Source .bashrc to load NVM and other environment variables, then run yarn install
    cmd = 'bash -c "source ~/.bashrc && yarn install"'
    return vps.run_command_live(cmd, "/var/www/html")


def pip_install_requirements_dev(vps: VPSConnector):
    """Run 'bash -e bin/py -m pip install -r requirements-dev.txt' in the project directory on the VPS."""
    return vps.run_command_live(
        "bash -e bin/py -m pip install -r requirements-dev.txt", "/var/www/html"
    )


def register():
    """Register the menu item for pulling the latest code."""
    return [
        {
            "label": "Pull latest code (git pull)",
            "action": pull_latest_code,
        },
        {
            "label": "Run composer update",
            "action": composer_update,
        },
        {
            "label": "Run yarn install",
            "action": yarn_install,
        },
        {
            "label": "Install Python dev requirements",
            "action": pip_install_requirements_dev,
        },
    ]
