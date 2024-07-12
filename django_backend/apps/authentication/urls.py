# urls.py
from django.urls import path
from .views import UserLoginAPIView, user_logout_api, user_login_api

urlpatterns = [
    path('login', UserLoginAPIView.as_view(), name='login'),
    path('register', user_login_api, name='register'),
    path('logout', user_logout_api, name='logout'),
]
