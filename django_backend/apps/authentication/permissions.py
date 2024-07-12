from rest_framework import permissions


class AdminOnlyPermission(permissions.BasePermission):
    def has_permission(self, request, view):
        # Only allow admin users to create new users
        return request.user and request.user.is_superuser
