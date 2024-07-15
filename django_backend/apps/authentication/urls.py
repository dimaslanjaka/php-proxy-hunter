from django.urls import path
from . import views

urlpatterns = [
    path('create', views.CreateUserAPIView.as_view(), name='create-user'),
    path('login', views.LoginUserAPIView.as_view(), name='login-user'),
    # fallback login/?next=
    path('login/', views.LoginUserAPIView.as_view(), name='login-user'),
    path('logout', views.LogoutUserAPIView.as_view(), name='logout-user'),
    path('status', views.CurrentUserStatusView.as_view(), name='current_user_status')
]
