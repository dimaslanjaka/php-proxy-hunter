"""
Django settings.

Generated by 'django-admin startproject' using Django 2.1.5.

For more information on this file, see
https://docs.djangoproject.com/en/2.1/topics/settings/

For the full list of settings and their values, see
https://docs.djangoproject.com/en/2.1/ref/settings/
"""

import os
import sys
from datetime import datetime, timedelta

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
import sys

import dotenv

from src.func import delete_path, get_relative_path, write_file
from src.func_platform import is_debug


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
    "django.middleware.cache.UpdateCacheMiddleware",
    "django.middleware.cache.FetchFromCacheMiddleware",
]

# file-based caching
if DEBUG:
    CACHES = {
        "default": {
            "BACKEND": "django.core.cache.backends.dummy.DummyCache",
        }
    }
else:
    CACHES = {
        "default": {
            "BACKEND": "django.core.cache.backends.filebased.FileBasedCache",
            "LOCATION": get_relative_path("tmp/django_cache"),
        }
    }

ROOT_URLCONF = "django_backend.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [
            path
            for path in [
                os.path.join(BASE_DIR, "django_backend/apps/core/templates"),
                os.path.join(BASE_DIR, "django_backend/apps/axis/templates"),
                os.path.join(BASE_DIR, "django_backend/apps/proxy/templates"),
            ]
            if os.path.exists(path)
        ],
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
# number threads will be used in django
WORKER_THREADS = 4 if not is_debug() else 10

# Logging settings
# dont remove `from logging.handlers import TimedRotatingFileHandler`

LOGGING = {
    "version": 1,
    "disable_existing_loggers": False,
    "formatters": {
        "verbose": {
            "format": "{levelname} {asctime} {module} {message}",
            "style": "{",
        },
        "simple": {
            "format": "{levelname} {message}",
            "style": "{",
        },
    },
    "handlers": {
        "file": {
            "level": "DEBUG",
            "class": "logging.handlers.TimedRotatingFileHandler",
            "filename": os.path.join(
                BASE_DIR, f"tmp/logs/django-{datetime.now().strftime('%Y-%m-%d')}.log"
            ),
            "when": "midnight",  # Rotate logs every midnight
            "interval": 1,
            "backupCount": 7,  # Keep the last 7 days of logs
            "formatter": "verbose",
        },
    },
    "loggers": {
        "django": {
            "handlers": ["file"],
            "level": "DEBUG",
            "propagate": True,
        },
        "": {
            "handlers": ["file"],
            "level": os.getenv("DJANGO_LOG_LEVEL", "INFO"),
        },
    },
}

# disable directory listing
write_file(
    os.path.join(
        os.path.dirname(LOGGING["handlers"]["file"]["filename"]), "index.html"
    ),
    "",
)
# reset log file every restart
delete_path(LOGGING["handlers"]["file"]["filename"])

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

# Define the root directory for collected static files
STATIC_ROOT = os.path.join(BASE_DIR, "public/static")

# Define multiple directories for static files
STATICFILES_DIRS = [
    os.path.join(BASE_DIR, "django_backend/apps/core/statics"),
    os.path.join(BASE_DIR, "django_backend/apps/axis/statics"),
    os.path.join(BASE_DIR, "django_backend/apps/authentication/statics"),
    os.path.join(BASE_DIR, "django_backend/apps/proxy/statics"),
    os.path.join(BASE_DIR, "js"),
    os.path.join(BASE_DIR, "public"),
]

# Filter only existing directories
STATICFILES_DIRS = [path for path in STATICFILES_DIRS if os.path.exists(path)]
