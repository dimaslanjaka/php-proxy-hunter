from django.urls import path
import os, sys
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
from . import views

app_name = 'proxy'
urlpatterns = [
    path('', views.proxies_list, name='proxy_list'),
    path('check', views.trigger_check_proxy, name='check_proxy'),
]
