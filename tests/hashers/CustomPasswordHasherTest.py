import os
import sys
import pytest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))
os.environ.setdefault("DJANGO_SETTINGS_MODULE", "django_backend.settings")

from src.hashers.CustomPasswordHasher import CustomPasswordHasher


@pytest.fixture
def password():
    return "my_secure_password"


@pytest.fixture
def hasher():
    return CustomPasswordHasher()


def test_generate_salt(hasher):
    salt = hasher.salt()
    assert isinstance(salt, str)
    assert len(salt) > 0


def test_encode_and_verify(password, hasher):
    salt = hasher.salt()
    encoded = hasher.encode(password, salt)
    assert isinstance(encoded, str)
    assert len(encoded) > 0
    is_valid = hasher.verify(password, encoded)
    assert is_valid
