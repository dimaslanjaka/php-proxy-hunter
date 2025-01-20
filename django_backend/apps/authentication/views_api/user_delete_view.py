from django.contrib.auth.models import User
from django.db.models import Q
from django.shortcuts import get_object_or_404
from rest_framework.permissions import IsAdminUser
from rest_framework.response import Response
from rest_framework.views import APIView


class UserDeleteView(APIView):
    permission_classes = [IsAdminUser]

    def get_user_by_identifier(self, identifier):
        # Try to get user by ID if identifier is numeric
        if identifier.isdigit():  # Check if identifier is numeric
            return get_object_or_404(User, id=int(identifier))

        # Try to get user by username
        user = User.objects.filter(username=identifier).first()
        if user:
            return user

        # Try to get user by email
        user = User.objects.filter(email=identifier).first()
        if user:
            return user

        # If no user found, raise 404 error
        raise get_object_or_404(User, Q(username=identifier) | Q(email=identifier))  # type: ignore

    def get(self, request, identifier):
        user = self.get_user_by_identifier(identifier)
        user.delete()
        return Response({"message": f"User {identifier} deleted successfully"})

    def post(self, request):
        identifier = (
            request.data.get("id")
            or request.data.get("email")
            or request.data.get("username")
        )
        if not identifier:
            return Response({"error": "User identifier not provided"}, status=400)

        user = self.get_user_by_identifier(identifier)
        user.delete()
        return Response({"message": f"User {identifier} deleted successfully"})

    def delete(self, request, identifier):
        user = self.get_user_by_identifier(identifier)
        deleted_identifier = user.username  # You can use any identifier you prefer here
        user.delete()
        return Response({"message": f"User {deleted_identifier} deleted successfully"})
