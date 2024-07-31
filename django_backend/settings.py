"""
Django settings.

Generated by 'django-admin startproject' using Django 2.1.5.

For more information on this file, see
https://docs.djangoproject.com/en/2.1/topics/settings/

For the full list of settings and their values, see
https://docs.djangoproject.com/en/2.1/ref/settings/
"""

import huey
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from datetime import datetime, timedelta

import dotenv
from userscripts.parse_userscript import extract_domains_from_userscript
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
SECRET_KEY = os.getenv(
    "DJANGO_SECRET_KEY", "-)ir)&2lz9o41=qsd7pbzl+uv%1tgf+$%ddvz9bbw6_(exk)(f"
)

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = is_debug()

# production and development hosts/domains
ALLOWED_HOSTS = [
    "localhost",
    "sh.webmanajemen.com",
    "dev.webmanajemen.com",
    "23.94.85.180",
    "127.0.0.1",
    "192.168.1.75",
]

# production port
PRODUCTION_PORT = 8443

SECURE_PROXY_SSL_HEADER = ("HTTP_X_FORWARDED_PROTO", "https")

# Determine if the environment is secure (HTTPS)
IS_SECURE = (
    True  # Set to True if using HTTPS (runserver_plus), False otherwise (runserver)
)

# Redirect all HTTP requests to HTTPS
SECURE_SSL_REDIRECT = IS_SECURE

# Use Secure Cookies only if in a secure (HTTPS) environment
SESSION_COOKIE_SECURE = IS_SECURE
CSRF_COOKIE_SECURE = IS_SECURE
CSRF_COOKIE_HTTPONLY = True  # Recommended for better security

# Allow cookies to be sent with cross-site requests
SESSION_COOKIE_SAMESITE = "None"

# Generate CSRF_TRUSTED_ORIGINS by combining protocols, domains, and ports
CSRF_TRUSTED_ORIGINS = [
    f"{protocol}://{domain}:{port}"
    for domain in ALLOWED_HOSTS
    for protocol in ["http", "https"]
    for port in [8000, 8880, PRODUCTION_PORT]
]
userscript_path = get_relative_path("userscripts/universal.user.js")
# Extract domains from the userscript
userscript_domains = extract_domains_from_userscript(userscript_path)

# Prefix each domain with both http and https
userscript_origins = [f"http://{domain}" for domain in userscript_domains] + [
    f"https://{domain}" for domain in userscript_domains
]

# Remove duplicates if you want only unique origins
userscript_origins = list(set(userscript_origins))
CSRF_TRUSTED_ORIGINS += userscript_origins
CORS_ALLOWED_ORIGINS = userscript_origins

# CORS
# CORS_ALLOW_ALL_ORIGINS = True
CORS_ALLOW_CREDENTIALS = True

# Set the session cookie age to 1 week (7 days)
SESSION_COOKIE_AGE = 60 * 60 * 24 * 7

# Keep the session active even after the browser is closed
SESSION_EXPIRE_AT_BROWSER_CLOSE = False

# Save the session data on every request
SESSION_SAVE_EVERY_REQUEST = True

# Application definition

INSTALLED_APPS = [
    "huey.contrib.djhuey",
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

# Google OAuth2 configuration
G_CLIENT_ID = os.getenv("G_CLIENT_ID")
G_CLIENT_SECRET = os.getenv("G_CLIENT_SECRET")
G_PROJECT_ID = os.getenv("G_PROJECT_ID")
G_REDIRECT_URI = os.getenv("G_REDIRECT_URI")

# middleware classes based on priority
MIDDLEWARE = [
    "corsheaders.middleware.CorsMiddleware",
    "django_backend.middleware.CustomCsrfExemptMiddleware",
    "django_backend.middleware.CsrfExemptCsrfViewMiddleware",
    "django_backend.middleware.FaviconMiddleware",
    "django.middleware.security.SecurityMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",  # Default session middleware
    "django_backend.sessions.init.SessionMiddleware",  # Custom session middleware
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
    "django_backend.middleware.MinifyHTMLMiddleware",  # minify html
    "django_backend.middleware.SitemapMiddleware",  # write sitemap.txt
]

if not DEBUG:
    MIDDLEWARE += [
        "django.middleware.cache.UpdateCacheMiddleware",
        "django.middleware.cache.FetchFromCacheMiddleware",
    ]

# file-based caching only for production
if not DEBUG:
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
                os.path.join(BASE_DIR, "django_backend/apps/authentication/templates"),
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
# limit threads to operate django_backend\apps\proxy\views.py
LIMIT_THREADS = 4 if not is_debug() else 10
# limit proxies to be checked in 1 thread
LIMIT_PROXIES_CHECK = 100
# limit duplicated ips to be checked in 1 thread
LIMIT_FILTER_CHECK = 100
# skip limitation for admin
UNLIMITED_FOR_ADMIN = True

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
        "console": {
            "level": "DEBUG",
            "class": "logging.StreamHandler",
            "formatter": "simple",
        },
        "file": {
            "level": "INFO",  # Adjust log level as needed
            "class": "logging.handlers.TimedRotatingFileHandler",
            "filename": os.path.join(
                BASE_DIR, f"tmp/logs/django-{datetime.now().strftime('%Y-%m-%d')}.log"
            ),
            "when": "midnight",
            "interval": 1,
            "backupCount": 7,
            "formatter": "verbose",
        },
    },
    "loggers": {
        "django": {
            "handlers": ["file"],
            "level": "INFO",  # Adjust log level as needed
            "propagate": True,
        },
        "django.server": {
            "handlers": ["file"],
            "level": "INFO",
            "propagate": False,
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
        "NAME": os.path.join(BASE_DIR, "tmp/database.sqlite"),
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

HUEY = huey.SqliteHuey(
    name="django_huey", filename=get_relative_path("tmp/huey.db"), immediate=False
)

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

# SESSION_ENGINE = "django.contrib.sessions.backends.db"  # Default session backend
SESSION_ENGINE = "django_backend.sessions.init"
SESSION_COOKIE_NAME = "nix"  # Default cookie name
SESSION_EXPIRE_AT_BROWSER_CLOSE = False  # Session expires on browser close
SESSION_COOKIE_AGE = 5 * 60 * 60  # Session cookie age in seconds
SESSION_FILE_PATH = get_relative_path("tmp/sessions")

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
