from typing import Optional

from django.contrib.auth import authenticate, get_user_model, login, logout
from django.contrib.auth.decorators import login_required
from django.contrib.auth.models import User
from django.db.models import Q
from django.http import HttpRequest, JsonResponse
from django.shortcuts import get_object_or_404
from django.utils.decorators import method_decorator
from rest_framework import status
from rest_framework.permissions import AllowAny, IsAdminUser, IsAuthenticated
from rest_framework.response import Response
from rest_framework.views import APIView

from .serializers import UserRegistrationSerializer

UserModel = get_user_model()


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
        raise get_object_or_404(User, Q(username=identifier) | Q(email=identifier))

    def get(self, request, identifier):
        user = self.get_user_by_identifier(identifier)
        user.delete()
        return Response({'message': f'User {identifier} deleted successfully'})

    def post(self, request):
        identifier = request.data.get('id') or request.data.get('email') or request.data.get('username')
        if not identifier:
            return Response({'error': 'User identifier not provided'}, status=400)

        user = self.get_user_by_identifier(identifier)
        user.delete()
        return Response({'message': f'User {identifier} deleted successfully'})

    def delete(self, request, identifier):
        user = self.get_user_by_identifier(identifier)
        deleted_identifier = user.username  # You can use any identifier you prefer here
        user.delete()
        return Response({'message': f'User {deleted_identifier} deleted successfully'})


class CreateUserAPIView(APIView):
    permission_classes = [IsAdminUser]  # Only admin can create users

    def post(self, request):
        serializer = UserRegistrationSerializer(data=request.data)
        if serializer.is_valid():
            serializer.save()
            return Response({"message": "User created successfully"}, status=status.HTTP_201_CREATED)
        return Response(serializer.errors, status=status.HTTP_304_NOT_MODIFIED)

    def get(self, request: HttpRequest, username: Optional[str] = None, password: Optional[str] = None):
        serializer = UserRegistrationSerializer(data={'username': username, 'password': password})
        if serializer.is_valid():
            serializer.save()
            return Response({"message": "User created successfully"}, status=status.HTTP_201_CREATED)
        return Response(serializer.errors, status=status.HTTP_304_NOT_MODIFIED)


class LoginUserAPIView(APIView):
    permission_classes = [AllowAny]  # Allow any user to login

    def post(self, request: HttpRequest):
        username = request.data.get('username') or None
        password = request.data.get('password') or None

        # Try to authenticate with username first
        user = authenticate(request, username=username, password=password)

        # If username authentication fails, try with email
        if not user:
            user = authenticate(request, email=username, password=password)

        if user:
            login(request, user)
            if user.is_superuser:
                return JsonResponse({'message': 'Login successful. Welcome, admin.'}, status=status.HTTP_200_OK)
            return JsonResponse({'message': 'Login successful.'}, status=status.HTTP_200_OK)
        else:
            return JsonResponse({'error': 'Invalid credentials.'}, status=status.HTTP_401_UNAUTHORIZED)

    def get(self, request: HttpRequest, username: Optional[str] = None, password: Optional[str] = None):
        if not username or not password:
            return JsonResponse({'error': 'Unauthorized'}, status=status.HTTP_401_UNAUTHORIZED)

        if not UserModel.objects.filter(username=username):
            return JsonResponse({'error': 'User not found'}, status=status.HTTP_401_UNAUTHORIZED)

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
            return JsonResponse({'message': 'Login successful.'}, status=status.HTTP_200_OK)
        else:
            return JsonResponse({'error': 'Invalid credentials.'}, status=status.HTTP_401_UNAUTHORIZED)


class LogoutUserAPIView(APIView):
    permission_classes = [IsAuthenticated]  # Only authenticated users can logout

    def post(self, request: HttpRequest):
        logout(request)
        return Response({'message': 'Logout successful.'}, status=status.HTTP_200_OK)

    def get(self, request: HttpRequest):
        logout(request)
        return Response({'message': 'Logout successful.'}, status=status.HTTP_200_OK)
