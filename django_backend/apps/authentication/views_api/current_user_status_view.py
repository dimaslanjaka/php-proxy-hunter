from django.conf import settings
from django.contrib.auth.models import User
from django.http import HttpRequest, JsonResponse
from rest_framework.permissions import IsAuthenticated
from rest_framework.views import APIView
from django_backend.apps.authentication.utils import get_user_with_fields


class CurrentUserStatusView(APIView):
    permission_classes = [IsAuthenticated]

    def get(self, request: HttpRequest):
        user = request.user
        data = get_user_with_fields(user)  # type: ignore
        data["SID"] = request.session.session_key
        data["is_admin"] = (
            request.user.is_authenticated
            and User.objects.get(pk=request.user.pk).is_staff
            and settings.UNLIMITED_FOR_ADMIN
        )
        return JsonResponse(data)
