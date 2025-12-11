import sys
from pathlib import Path
from typing import Optional
import os
import json
import hashlib
import base64
from cryptography.fernet import Fernet

# Ensure repository root is on sys.path so imports work
_REPO_ROOT = str(Path(__file__).resolve().parent.parent.parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

from src.func import get_relative_path

# App secret key - randomly generated and hardcoded for this installation
# Generated with: secrets.token_hex(32)
_APP_SECRET = "a7f3c9e2b1d4f6a8c3e5b2d9f7a4c1e8b5d2f9a6c3e1b8d5f2a9c6e3b0d7f4a"


def _get_encryption_key() -> bytes:
    """Generate a consistent encryption key based on machine + app secret."""
    try:
        # Get machine identifier (Windows)
        if sys.platform == "win32":
            import subprocess

            machine_id = (
                subprocess.check_output("wmic csproduct get uuid", shell=True)
                .decode()
                .split("\n")[1]
                .strip()
            )
        else:
            # Linux/Mac fallback
            import uuid

            machine_id = str(uuid.getnode())
    except Exception:
        # Fallback if machine ID fails
        machine_id = "default-machine"

    # Combine machine ID + app secret
    combined = f"{machine_id}:{_APP_SECRET}".encode()
    # Hash and encode as base64 (Fernet requires 32-byte key)
    key_hash = hashlib.sha256(combined).digest()
    return base64.urlsafe_b64encode(key_hash)


def _get_settings_file() -> str:
    """Get or create the settings file path in app data directory."""
    app_dir = get_relative_path(".data")
    os.makedirs(app_dir, exist_ok=True)
    return os.path.join(app_dir, "app.settings")


def _load_settings_dict() -> dict:
    """Load and decrypt all settings from JSON file. Returns empty dict if file doesn't exist."""
    settings_file = _get_settings_file()
    try:
        if os.path.exists(settings_file):
            with open(settings_file, "rb") as f:
                encrypted_data = f.read()

            # Decrypt using Fernet
            cipher = Fernet(_get_encryption_key())
            decrypted_data = cipher.decrypt(encrypted_data)
            return json.loads(decrypted_data.decode())
    except Exception:
        pass
    return {}


def _save_settings_dict(data: dict) -> bool:
    """Encrypt and save all settings to JSON file."""
    try:
        settings_file = _get_settings_file()

        # Serialize to JSON
        json_data = json.dumps(data, indent=2).encode()

        # Encrypt using Fernet
        cipher = Fernet(_get_encryption_key())
        encrypted_data = cipher.encrypt(json_data)

        # Write encrypted data
        with open(settings_file, "wb") as f:
            f.write(encrypted_data)
        return True
    except Exception:
        return False


def save_text(key: str, text: str) -> None:
    """Save a string value under the given key using encrypted JSON file storage."""
    try:
        settings = _load_settings_dict()
        settings[key] = text
        _save_settings_dict(settings)
    except Exception:
        # Silently ignore failures to avoid crashing UI callers.
        return


def load_text(key: str) -> Optional[str]:
    """Load a string value for the given key from encrypted JSON file storage. Returns None if not found or on error."""
    try:
        settings = _load_settings_dict()
        value = settings.get(key)
        return value if value is not None else None
    except Exception:
        return None
