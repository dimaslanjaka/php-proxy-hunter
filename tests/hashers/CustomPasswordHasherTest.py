import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))

from src.hashers.CustomPasswordHasher import CustomPasswordHasher

password = "my_secure_password"
salt = CustomPasswordHasher().salt()
encoded = CustomPasswordHasher().encode(password, salt)
is_valid = CustomPasswordHasher().verify(password, encoded)
print(salt, encoded, is_valid, sep="\n")
