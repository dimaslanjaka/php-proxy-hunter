from rest_framework import permissions
from django.http import HttpRequest


class AdminOnlyPermission(permissions.BasePermission):
    def has_permission(self, request: HttpRequest, view):
        # Only allow admin users to create new users
        return request.user and request.user.is_superuser
