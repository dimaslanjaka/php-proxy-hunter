from django.contrib.auth import logout
from django.http import HttpRequest
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response
from rest_framework.views import APIView


class LogoutUserAPIView(APIView):
    permission_classes = [IsAuthenticated]  # Only authenticated users can logout

    def post(self, request: HttpRequest):
        logout(request)
        return Response({"message": "Logout successful."}, status=status.HTTP_200_OK)

    def get(self, request: HttpRequest):
        logout(request)
        return Response({"message": "Logout successful."}, status=status.HTTP_200_OK)
