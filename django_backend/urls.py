import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from django.conf import settings
from django.contrib import admin
from django.http import HttpResponse, HttpRequest
from django.urls import include, path
from django.views.static import serve
from django.conf.urls.static import static
from src.func import get_relative_path


def index(request: HttpRequest):
    index_file_path = os.path.join(settings.BASE_DIR, "public", "index.html")
    with open(index_file_path, "r") as file:
        return HttpResponse(file.read(), content_type="text/html")


urlpatterns = [
    path("auth/", include("django_backend.apps.authentication.urls")),
    path("proxy/", include("django_backend.apps.proxy.urls")),
    path("admin/", admin.site.urls),
    path(
        "js/<path:path>/",
        serve,
        {"document_root": os.path.join(settings.BASE_DIR, "js")},
    ),
    path("", index, name="index"),
] # + static(settings.STATIC_URL, document_root=settings.STATIC_ROOT)

if os.path.exists(get_relative_path("django_backend/apps/axis/urls.py")):
    urlpatterns.append(path("axis/", include("django_backend.apps.axis.urls")))
    # path('axis/static/<path:path>/', serve, {'document_root': os.path.join(settings.BASE_DIR, 'xl/axisnet')}),
