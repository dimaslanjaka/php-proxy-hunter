from django.contrib.auth.hashers import BasePasswordHasher
import hashlib
import os


class CustomPasswordHasher(BasePasswordHasher):
    algorithm = "custom"

    def salt(self):
        return os.urandom(16).hex()

    def encode(self, password, salt):
        # Combine password and salt, then hash using SHA256
        return (
            hashlib.sha256((password + salt).encode("utf-8")).hexdigest() + "$" + salt
        )

    def verify(self, password, encoded):
        # Verify password by comparing hashes
        password_hash, salt = encoded.rsplit("$", 1)
        return (
            password_hash
            == hashlib.sha256((password + salt).encode("utf-8")).hexdigest()
        )

    def safe_summary(self, encoded):
        password_hash, salt = encoded.rsplit("$", 1)
        return {
            "algorithm": self.algorithm,
            "salt": salt,
        }
