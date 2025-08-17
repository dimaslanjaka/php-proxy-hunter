from django.contrib.auth.hashers import BasePasswordHasher
import hashlib
import os
from django.conf import settings


class CustomPasswordHasher(BasePasswordHasher):
    algorithm = "custom"

    def salt(self):
        # Use SECRET_KEY to generate deterministic salt
        secret_key = getattr(settings, "SECRET_KEY", "default_secret_key")
        return hashlib.sha256(secret_key.encode("utf-8")).hexdigest()[:16]

    def encode(self, password, salt):
        # Combine password and salt, then hash using SHA256
        return (
            hashlib.sha256((password + salt).encode("utf-8")).hexdigest() + "$" + salt
        )

    def verify(self, password, encoded):
        # Verify password by comparing hashes
        if "$" not in encoded:
            # Invalid encoded format, avoid ValueError
            return False
        parts = encoded.rsplit("$", 1)
        if len(parts) != 2 or not parts[0] or not parts[1]:
            # Either part is missing or empty, invalid format
            return False
        password_hash = parts[0]
        salt = parts[1]
        return (
            password_hash
            == hashlib.sha256((password + salt).encode("utf-8")).hexdigest()
        )

    def safe_summary(self, encoded):
        parts = encoded.rsplit("$", 1)
        salt = parts[1] if len(parts) == 2 else ""
        return {
            "algorithm": self.algorithm,
            "salt": salt,
        }
