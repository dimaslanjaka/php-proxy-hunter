from typing import Optional
from django.contrib.auth import authenticate, login
from django.http import HttpRequest, JsonResponse
from django.shortcuts import render
from rest_framework import status
from rest_framework.permissions import AllowAny
from rest_framework.views import APIView
from django.contrib.auth import get_user_model

UserModel = get_user_model()


class LoginUserAPIView(APIView):
    permission_classes = [AllowAny]  # Allow any user to login

    def post(self, request: HttpRequest):
        username = request.data.get("username") or None
        password = request.data.get("password") or None

        # Try to authenticate with username first
        user = authenticate(request, username=username, password=password)

        # If username authentication fails, try with email
        if not user:
            user = authenticate(request, email=username, password=password)

        if user:
            login(request, user)
            if user.is_superuser:
                return JsonResponse(
                    {"message": "Login successful. Welcome, admin."},
                    status=status.HTTP_200_OK,
                )
            return JsonResponse(
                {"message": "Login successful."}, status=status.HTTP_200_OK
            )
        else:
            return JsonResponse(
                {"error": "Invalid credentials."}, status=status.HTTP_401_UNAUTHORIZED
            )

    def get(
        self,
        request: HttpRequest,
        username: Optional[str] = None,
        password: Optional[str] = None,
    ):
        if not username or not password:
            return render(request, "login.html")

        if not UserModel.objects.filter(username=username):
            return JsonResponse(
                {"error": "User not found"}, status=status.HTTP_401_UNAUTHORIZED
            )

        print(f"trying login {username}:{password}")

        # Try to authenticate with username first
        user = authenticate(request, username=username, password=password)
        print(user)

        # If username authentication fails, try with email
        if not user:
            user = authenticate(request, email=username, password=password)
            print(user)

        if user:
            login(request, user)
            return JsonResponse(
                {"message": "Login successful."}, status=status.HTTP_200_OK
            )
        else:
            return JsonResponse(
                {"error": "Invalid credentials."}, status=status.HTTP_401_UNAUTHORIZED
            )
