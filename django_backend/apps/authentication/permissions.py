from rest_framework import permissions
from django.http import HttpRequest


class AdminOnlyPermission(permissions.BasePermission):
    def has_permission(self, request: HttpRequest, view):  # type: ignore
        # Only allow admin users to create new users
        return bool(
            request.user
            and hasattr(request.user, "is_superuser")
            and request.user.is_superuser  # type: ignore
        )
