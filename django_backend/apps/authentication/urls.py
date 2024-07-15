import os
import sys
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))
from django.urls import path
from . import views
from src.func import is_debug

urlpatterns = [
    path('create', views.CreateUserAPIView.as_view(), name='create-user'),
    path('login', views.LoginUserAPIView.as_view(), name='login-user'),
    # fallback login/?next=
    path('login/', views.LoginUserAPIView.as_view(), name='login-user'),
    path('logout', views.LogoutUserAPIView.as_view(), name='logout-user'),
    path('status', views.CurrentUserStatusView.as_view(), name='current_user_status')
]

if is_debug():
    # add simple auth for development mode
    urlpatterns.append(path('login/<username>/<password>', views.LoginUserAPIView.as_view(), name='login-user'))
    urlpatterns.append(path('create/<username>/<password>', views.CreateUserAPIView.as_view(), name='create-user'))
