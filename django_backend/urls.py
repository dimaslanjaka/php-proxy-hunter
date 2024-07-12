"""rattlesnake URL Configuration

The `urlpatterns` list routes URLs to views. For more information please see:
    https://docs.djangoproject.com/en/2.1/topics/http/urls/
Examples:
Function views
    1. Add an import:  from my_app import views
    2. Add a URL to urlpatterns:  path('', views.home, name='home')
Class-based views
    1. Add an import:  from other_app.views import Home
    2. Add a URL to urlpatterns:  path('', Home.as_view(), name='home')
Including another URLconf
    1. Import the include() function: from django.urls import include, path
    2. Add a URL to urlpatterns:  path('blog/', include('blog.urls'))
"""

import os, sys
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
from django.conf import settings
from django.contrib import admin
from django.urls import include, path
from django.views.static import serve
from src.func import get_relative_path

urlpatterns = [
    path('auth/', include('django_backend.apps.authentication.urls')),
    path('proxy/', include('django_backend.apps.proxy.urls')),
    path('admin/', admin.site.urls),
    path('js/<path:path>/', serve, {'document_root': os.path.join(settings.BASE_DIR, 'js')})
]

if os.path.exists(get_relative_path('django_backend/apps/axis/urls.py')):
    urlpatterns.append(path('axis/', include('django_backend.apps.axis.urls')))
    # path('axis/static/<path:path>/', serve, {'document_root': os.path.join(settings.BASE_DIR, 'xl/axisnet')}),
