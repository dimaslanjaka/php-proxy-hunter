import os
import sys
from django.test import TestCase

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))
os.environ.setdefault("DJANGO_SETTINGS_MODULE", "django_backend.settings")

from src.hashers.CustomPasswordHasher import CustomPasswordHasher

__all__ = ["TestCase"]

password = "my_secure_password"
salt = CustomPasswordHasher().salt()
encoded = CustomPasswordHasher().encode(password, salt)
is_valid = CustomPasswordHasher().verify(password, encoded)
print(salt, encoded, is_valid, sep="\n")
