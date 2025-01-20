from typing import Union
from django.contrib.auth import get_user_model
from .models import UserFields
from django.contrib.auth.models import AbstractUser, AnonymousUser, AbstractBaseUser

UserModel = get_user_model()


def get_user_with_fields(
    identifier: Union[
        int, str, type[AnonymousUser], type[AbstractUser], type[AbstractBaseUser]
    ]
) -> dict:
    """
    Retrieves user data along with their balance (saldo) based on the given identifier.

    Parameters:
    - identifier (Union[int, str, UserModel]): Identifier to fetch the user. Can be user ID (int),
      username (str), or UserModel instance.

    Returns:
    - dict: Dictionary containing user details including saldo (balance).
    """
    if isinstance(identifier, str):
        # Assume `identifier` is a username
        user = UserModel.objects.get(username=identifier)
    elif isinstance(identifier, int):
        # Assume `identifier` is a user ID
        user = UserModel.objects.get(id=identifier)
    elif isinstance(identifier, UserModel):
        user = identifier
    else:
        raise ValueError(
            "Invalid identifier type. Expected str (username), int (user ID), or User instance."
        )

    try:
        user_balance = UserFields.objects.get(user=user)
        user_data = {
            "username": user.username,
            "email": user.email,
            "first_name": user.first_name,
            "last_name": user.last_name,
            "is_active": user.is_active,
            "date_joined": user.date_joined.strftime("%Y-%m-%d %H:%M:%S"),
            "saldo": float(
                user_balance.saldo
            ),  # Convert DecimalField to float for JSON response
        }
    except UserFields.DoesNotExist:
        user_data = {
            "username": user.username,
            "email": user.email,
            "first_name": user.first_name,
            "last_name": user.last_name,
            "is_active": user.is_active,
            "date_joined": user.date_joined.strftime("%Y-%m-%d %H:%M:%S"),
            "saldo": 0.0,  # Default balance if not found
        }

    return user_data


def parse_csrf_token_from_cookie_file(cookie_file_path):
    csrf_token = None
    with open(cookie_file_path, "r") as f:
        for line in f:
            if line.startswith("#"):
                continue  # Skip comment lines
            parts = line.strip().split("\t")
            if len(parts) >= 7 and parts[5] == "csrftoken":
                csrf_token = parts[6]
                break
    return csrf_token
