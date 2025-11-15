from PySide6.QtCore import QSettings
from typing import Optional


def save_text(
    key: str, text: str, org: str = "php-proxy-hunter", app: str = "proxy-checker"
) -> None:
    """Save a string value under the given key using QSettings."""
    try:
        settings = QSettings(org, app)
        settings.setValue(key, text)
    except Exception:
        # Silently ignore failures to avoid crashing UI callers.
        return


def load_text(
    key: str, org: str = "php-proxy-hunter", app: str = "proxy-checker"
) -> Optional[str]:
    """Load a string value for the given key. Returns None if not found or on error."""
    try:
        settings = QSettings(org, app)
        value = settings.value(key, None)
        if value is None:
            return None
        return str(value)
    except Exception:
        return None
