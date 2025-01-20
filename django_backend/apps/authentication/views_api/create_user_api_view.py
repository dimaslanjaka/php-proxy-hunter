from typing import Optional
from django.http import HttpRequest
from rest_framework import status
from rest_framework.permissions import IsAdminUser
from rest_framework.response import Response
from rest_framework.views import APIView
from django_backend.apps.authentication.serializers import UserRegistrationSerializer


class CreateUserAPIView(APIView):
    permission_classes = [IsAdminUser]  # Only admin can create users

    def post(self, request):
        serializer = UserRegistrationSerializer(data=request.data)
        if serializer.is_valid():
            serializer.save()
            return Response(
                {"message": "User created successfully"}, status=status.HTTP_201_CREATED
            )
        return Response(serializer.errors, status=status.HTTP_304_NOT_MODIFIED)

    def get(
        self,
        request: HttpRequest,
        username: Optional[str] = None,
        password: Optional[str] = None,
    ):
        serializer = UserRegistrationSerializer(
            data={"username": username, "password": password}
        )
        if serializer.is_valid():
            serializer.save()
            return Response(
                {"message": "User created successfully"}, status=status.HTTP_201_CREATED
            )
        return Response(serializer.errors, status=status.HTTP_304_NOT_MODIFIED)
