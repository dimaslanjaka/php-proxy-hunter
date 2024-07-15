from typing import Optional

from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.http import HttpRequest, JsonResponse
from django.utils.decorators import method_decorator
from rest_framework import status
from rest_framework.permissions import AllowAny, IsAdminUser, IsAuthenticated
from rest_framework.response import Response
from rest_framework.views import APIView

from .serializers import CustomUserSerializer


class CurrentUserStatusView(APIView):
    permission_classes = [IsAuthenticated]

    @method_decorator(login_required)
    def get(self, request: HttpRequest):
        user = request.user
        user_data = {
            'username': user.username,
            'email': user.email,
            'first_name': user.first_name,
            'last_name': user.last_name,
            'is_active': user.is_active,
            'date_joined': user.date_joined.strftime('%Y-%m-%d %H:%M:%S')
        }
        return JsonResponse(user_data)


class CreateUserAPIView(APIView):
    permission_classes = [IsAdminUser]  # Only admin can create users

    def post(self, request):
        serializer = CustomUserSerializer(data=request.data)
        if serializer.is_valid():
            serializer.save()
            return Response(serializer.data, status=status.HTTP_201_CREATED)
        return Response(serializer.errors, status=status.HTTP_400_BAD_REQUEST)


class LoginUserAPIView(APIView):
    permission_classes = [AllowAny]  # Allow any user to login

    def post(self, request: HttpRequest):
        username = request.data.get('username')
        password = request.data.get('password')
        user = authenticate(username=username, password=password)
        if not user:
            user = authenticate(email=username, password=password)
        if user:
            login(request, user)
            if user.is_superuser:
                return JsonResponse({'message': 'Login successful. Welcome, admin.'}, status=status.HTTP_200_OK)
            return JsonResponse({'message': 'Login successful.'}, status=status.HTTP_200_OK)
        else:
            return JsonResponse({'error': 'Invalid credentials.'}, status=status.HTTP_401_UNAUTHORIZED)

    def get(self, request: HttpRequest, username: Optional[str] = None, password: Optional[str] = None):
        if not username or not password:
            return JsonResponse({'error': 'Method Not Allowed'}, status=status.HTTP_405_METHOD_NOT_ALLOWED)


class LogoutUserAPIView(APIView):
    permission_classes = [IsAuthenticated]  # Only authenticated users can logout

    def post(self, request: HttpRequest):
        logout(request)
        return Response({'message': 'Logout successful.'}, status=status.HTTP_200_OK)

    def get(self, request: HttpRequest):
        logout(request)
        return Response({'message': 'Logout successful.'}, status=status.HTTP_200_OK)
