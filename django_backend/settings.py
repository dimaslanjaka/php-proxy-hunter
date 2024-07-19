"""
Django settings for rattlesnake project.

Generated by 'django-admin startproject' using Django 2.1.5.

For more information on this file, see
https://docs.djangoproject.com/en/2.1/topics/settings/

For the full list of settings and their values, see
https://docs.djangoproject.com/en/2.1/ref/settings/
"""

from datetime import timedelta
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.func_platform import is_debug
from src.func import get_relative_path
import dotenv

# Build paths inside the project like this: os.path.join(BASE_DIR, ...)
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# # load environment variables from .env
dotenv_file = os.path.join(BASE_DIR, ".env")
if os.path.isfile(dotenv_file):
    dotenv.load_dotenv(dotenv_file)

# Quick-start development settings - unsuitable for production
# See https://docs.djangoproject.com/en/2.1/howto/deployment/checklist/

# SECURITY WARNING: keep the secret key used in production secret!
SECRET_KEY = "-)ir)&2lz9o41=qsd7pbzl+uv%1tgf+$%ddvz9bbw6_(exk)(f"

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = is_debug()

ALLOWED_HOSTS = [
    "localhost",
    "sh.webmanajemen.com",
    "dev.webmanajemen.com",
    "23.94.85.180",
    "127.0.0.1",
]

# Application definition

INSTALLED_APPS = [
    "channels",
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "corsheaders",
    "django_extensions",
    "rest_framework",
    "django_backend.apps.authentication",
    "django_backend.apps.core",
    "django_backend.apps.proxy",
]

if os.path.exists(get_relative_path("django_backend/apps/axis/urls.py")):
    INSTALLED_APPS.append("django_backend.apps.axis")

REST_FRAMEWORK = {
    "DEFAULT_AUTHENTICATION_CLASSES": [
        "rest_framework_simplejwt.authentication.JWTAuthentication",
        "rest_framework.authentication.BasicAuthentication",
        "rest_framework.authentication.SessionAuthentication",
    ],
    "DEFAULT_PERMISSION_CLASSES": [
        "rest_framework.permissions.IsAuthenticated",
    ],
    "DEFAULT_RENDERER_CLASSES": [
        "rest_framework.renderers.JSONRenderer",
    ],
    "EXCEPTION_HANDLER": "rest_framework.views.exception_handler",
}

SIMPLE_JWT = {
    "ACCESS_TOKEN_LIFETIME": timedelta(minutes=15),
    "REFRESH_TOKEN_LIFETIME": timedelta(days=1),
}

LOGIN_URL = "/auth/login"

MIDDLEWARE = [
    "corsheaders.middleware.CorsMiddleware",
    "django.middleware.security.SecurityMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
    "django_backend.middleware.MinifyHTMLMiddleware",
]

ROOT_URLCONF = "django_backend.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
            ],
        },
    },
]

# gunicorn --workers 4 --threads 2 django_backend.wsgi:application
WSGI_APPLICATION = "django_backend.wsgi.application"
ASGI_APPLICATION = "django_backend.asgi.application"

# Database
# https://docs.djangoproject.com/en/2.1/ref/settings/#databases

DATABASES = {
    "default": {
        "ENGINE": "django.db.backends.sqlite3",
        # development using src/database.sqlite without running php
        "NAME": os.path.join(
            BASE_DIR, "tmp/database.sqlite" if not is_debug() else "src/database.sqlite"
        ),
        # 'ENGINE': 'django.db.backends.mysql',
        # 'NAME': 'djangodatabase',
        # 'USER': 'root',
        # 'PASSWORD': '',
        # 'HOST': '127.0.0.1',
        # 'PORT': '80',
        "OPTIONS": {
            "timeout": 120,  # Adjust timeout value as needed
        },
    }
}

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"


# Password validation
# https://docs.djangoproject.com/en/2.1/ref/settings/#auth-password-validators

AUTH_PASSWORD_VALIDATORS = [
    {
        "NAME": "django.contrib.auth.password_validation.UserAttributeSimilarityValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.MinimumLengthValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.CommonPasswordValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.NumericPasswordValidator",
    },
]

# AUTH_USER_MODEL = 'authentication.CustomUser'

SESSION_ENGINE = "django.contrib.sessions.backends.db"  # Default session backend
SESSION_COOKIE_NAME = "nix"  # Default cookie name
SESSION_EXPIRE_AT_BROWSER_CLOSE = False  # Session expires on browser close
SESSION_COOKIE_AGE = 5 * 60 * 60  # Session cookie age in seconds

# Internationalization
# https://docs.djangoproject.com/en/2.1/topics/i18n/

LANGUAGE_CODE = "en-us"

TIME_ZONE = "Asia/Jakarta"

USE_I18N = True

USE_L10N = True

USE_TZ = True


# Static files (CSS, JavaScript, Images)
# https://docs.djangoproject.com/en/2.1/howto/static-files/

STATIC_URL = "/static/"
PROJECT_DIR = os.path.dirname(os.path.abspath(__file__))
STATIC_ROOT = os.path.join(PROJECT_DIR, "public")

# Define multiple directories for static files
STATICFILES_DIRS = [
    os.path.join(BASE_DIR, "django_backend/apps/axis/statics"),
    os.path.join(BASE_DIR, "django_backend/apps/authentication/statics"),
    os.path.join(BASE_DIR, "django_backend/apps/proxy/statics"),
    os.path.join(BASE_DIR, "js"),
    os.path.join(BASE_DIR, "public"),
]
# Filter only existing directories
existing_static_dirs = [path for path in STATICFILES_DIRS if os.path.exists(path)]

# Assign the filtered list back to STATICFILES_DIRS
STATICFILES_DIRS = existing_static_dirs
