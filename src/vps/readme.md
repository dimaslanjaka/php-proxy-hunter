# VPS Manager Tutorial

## Overview
VPS Manager is a Python-based tool for managing Virtual Private Servers. This tutorial will guide you through installation, setup, and basic usage.

## Installation

1. Ensure Python 3.6+ is installed on your system
2. Install required dependencies:
   ```cmd
   pip install paramiko
   ```
3. Clone or download the project to your local machine
4. Navigate to the project directory

## Configuration

### SFTP Configuration File

Before using VPS Manager, you must create a configuration file at `.vscode/sftp.json` in your project root. This file contains your VPS connection details.

**Required Configuration Format:**
```json
{
    "host": "your-vps-ip-or-domain.com",
    "port": 22,
    "username": "your-username",
    "password": "your-password",
    "remotePath": "/var/www/html"
}
```

**Alternative Configuration (using SSH key):**
```json
{
    "host": "your-vps-ip-or-domain.com",
    "port": 22,
    "username": "your-username",
    "privateKeyPath": "~/.ssh/id_rsa",
    "remotePath": "/var/www/html"
}
```

**Configuration Options:**
- `host` (required): VPS IP address or domain name
- `port` (optional): SSH port, defaults to 22
- `username` (required): SSH username
- `password` (optional): SSH password (use either this or privateKeyPath)
- `privateKeyPath` (optional): Path to SSH private key file
- `remotePath` (optional): Default remote directory, defaults to "/"

## Usage

### Running VPS Manager

From the project root directory, you can run VPS Manager using the provided batch script:

```cmd
bin\vps-mgr.cmd
```

Or run directly with Python:

```cmd
python src\vps\vps_manager.py
```

### Available Features

The VPS Manager provides the following functionality:

- **Pull latest code**: Run `git pull` on your VPS
- **Upload files/folders**: Transfer files from local to remote server
- **Download files**: Transfer files from remote to local
- **Run remote commands**: Execute commands on your VPS
- **Plugin system**: Extensible menu system via the `menus/` directory

### Menu Options

The VPS Manager dynamically loads menu options including:

1. **Pull latest code (git pull)** - Updates your VPS code repository
2. **Download Backups Folder** - Downloads `/var/www/html/backups/` to local `backups/` folder
3. **Upload isimple_tools** - Uploads and sets permissions for isimple tools
4. **Run isimple_tools** - Executes isimple tools on the VPS

### Examples

```cmd
# Start VPS Manager
bin\vps-mgr.cmd

# Example session:
Select an action to perform:
1. Pull latest code (git pull)
2. Download Backups Folder
3. Upload isimple_tools
4. Run isimple_tools
Enter your choice (1-4): 1
```

## Adding Custom Menu Items

You can extend VPS Manager by creating Python files in the `src/vps/menus/` directory. Each menu file should have a `register()` function that returns menu configuration:

```python
def register():
    return {"label": "My Custom Action", "action": my_action_function}

def my_action_function(vps: VPSConnector):
    # Your custom functionality here
    vps.run_command("your-command")
```

## Troubleshooting

- **"SFTP config file not found"**: Ensure `.vscode/sftp.json` exists in your project root
- **Connection errors**: Verify host, port, username, and credentials in sftp.json
- **Permission denied**: Check SSH key permissions or password authentication
- **Python path issues**: Ensure Python is in your system PATH
- **Module import errors**: Install required dependencies with `pip install paramiko`

## Security Notes

- Keep your `.vscode/sftp.json` file secure and never commit passwords to version control
- Consider using SSH keys instead of passwords for better security
- Add `.vscode/sftp.json` to your `.gitignore` file

## Support

For issues or questions, please check the project documentation or create an issue in the project repository.