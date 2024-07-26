import json
from urllib.parse import urlencode
import requests
from django.conf import settings
from django.contrib.auth import authenticate, login
from django.contrib.auth.models import User
from django.http import HttpRequest, JsonResponse
from django.shortcuts import redirect, render
import logging

logger = logging.getLogger(__name__)


def google_login(request: HttpRequest):
    auth_url = "https://accounts.google.com/o/oauth2/auth"
    params = {
        "client_id": settings.G_CLIENT_ID,
        "redirect_uri": settings.G_REDIRECT_URI,
        "response_type": "code",
        "scope": "profile email",
        "access_type": "offline",
        "prompt": "consent",
    }
    url = f"{auth_url}?{urlencode(params)}"
    return render(
        request,
        "login-google-outbound.html",
        {
            "params": json.dumps(
                {
                    "scope": params["scope"],
                    "redirect": params["redirect_uri"],
                },
                indent=2,
            ),
            "url": url,
        },
    )


def oauth2callback(request: HttpRequest):
    code = request.GET.get("code")
    if not code:
        return JsonResponse({"error": "No code provided"}, status=400)

    # Exchange code for access token
    token_url = "https://oauth2.googleapis.com/token"
    redirect_uri = settings.G_REDIRECT_URI
    data = {
        "code": code,
        "client_id": settings.G_CLIENT_ID,
        "client_secret": settings.G_CLIENT_SECRET,
        "redirect_uri": redirect_uri,
        "grant_type": "authorization_code",
    }
    response = requests.post(token_url, data=data)
    token_info = response.json()

    logger.debug(f"Token Response: {token_info}")

    # Check if token exchange was successful
    if response.status_code != 200:
        return JsonResponse(
            {
                "error": True,
                "message": token_info.get("error", "Unknown error"),
                "description": token_info.get("error_description", ""),
            },
            status=response.status_code,
        )

    access_token = token_info.get("access_token")
    if not access_token:
        return JsonResponse({"error": "No access token received"}, status=400)

    # Fetch user info from Google
    user_info_url = "https://www.googleapis.com/oauth2/v2/userinfo"
    headers = {"Authorization": f"Bearer {access_token}"}
    user_info_response = requests.get(user_info_url, headers=headers)
    user_info = user_info_response.json()

    logger.debug(f"User Info Response: {user_info}")

    # Check if user info request was successful
    if user_info_response.status_code != 200:
        return JsonResponse(
            {
                "error": True,
                "message": user_info.get("error", "Unknown error"),
                "description": user_info.get("error_description", ""),
            },
            status=user_info_response.status_code,
        )

    # Extract user info
    email = user_info.get("email")
    name = user_info.get("name")

    if not email:
        return JsonResponse({"error": "No email provided by Google"}, status=400)

    if name:
        name_parts = name.split()
        first_name = name_parts[0] if len(name_parts) > 0 else email.split("@")[0]
        last_name = " ".join(name_parts[1:]) if len(name_parts) > 1 else ""
    else:
        first_name = ""
        last_name = ""

    # Check if the user exists
    user = User.objects.filter(email=email).first()

    if not user:
        # Create a new user if they don't exist
        user = User.objects.create(
            username=email.split("@")[0],
            email=email,
            first_name=first_name,
            last_name=last_name,
        )

    # Authenticate user
    if user:
        login(request, user)
        return redirect("/auth/status")  # Redirect to the home page or wherever needed

    return JsonResponse(
        {"error": f"Authentication failed for user with email {email}."},
        status=400,
    )
