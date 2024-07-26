import logging
import socket
import time
from importlib import import_module
from django.conf import settings
from django.contrib.sessions.backends.base import UpdateError
from django.contrib.sessions.backends.file import SessionStore as FileSessionStore
from django.contrib.sessions.backends.db import SessionStore as DBSessionStore
from django.core.exceptions import SuspiciousOperation
from django.http import HttpRequest
from django.utils.cache import patch_vary_headers
from django.utils.deprecation import MiddlewareMixin
from django.utils.http import http_date
from django_backend.ServerUtils import ServerUtils


class session_encryption:
    def __init__(self, key=socket.gethostname()):
        self.key = key
        self.f9939a = list(range(256))

    def a(self, text):
        self.f9939a = list(range(256))
        i_arr = [ord(self.key[i % len(self.key)]) for i in range(256)]

        # Initialization phase
        i2 = 0
        for i3 in range(256):
            i4 = self.f9939a[i3]
            i2 = (i2 + i4 + i_arr[i3]) % 256
            self.f9939a[i3], self.f9939a[i2] = self.f9939a[i2], self.f9939a[i3]

        # Processing phase
        sb = []
        i5 = 0
        i6 = 0
        for i7 in range(len(text)):
            i5 = (i5 + 1) % 256
            i8 = self.f9939a[i5]
            i6 = (i6 + i8) % 256
            self.f9939a[i5], self.f9939a[i6] = self.f9939a[i6], self.f9939a[i5]
            sb.append(chr(self.f9939a[(self.f9939a[i5] + i8) % 256] ^ ord(text[i7])))

        return "".join(sb)

    def decrypt(self, encrypted):
        sb = []
        i = 0
        while i < len(encrypted):
            try:
                i2 = i + 2
                sb.append(chr(int(encrypted[i:i2], 16)))
                i = i2
            except ValueError:
                break

        return self.a("".join(sb))

    def encrypt(self, text):
        encrypted = self.a(text)
        return "".join(f"{ord(c):02x}" for c in encrypted)


encryptor = session_encryption()


class SessionStore(DBSessionStore):
    def create(self):
        # Generate a custom session key
        self._session_key = self._generate_custom_session_key()
        self.save(must_create=True)

    def _generate_custom_session_key(self):
        global encryptor
        # Retrieve user agent and IP from custom attributes
        user_agent = getattr(self, "user_agent", "")
        user_ip = getattr(self, "user_ip", "")

        # Create a unique string using user agent and IP
        unique_string = f"{user_agent}-{user_ip}"

        # Generate MD5 hash of the unique string
        # return encryptor.encrypt(unique_string)
        return unique_string


logger = logging.getLogger(__name__)


class SessionMiddlewareWithRequest(MiddlewareMixin):
    def __init__(self, get_response=None):
        self.get_response = get_response
        engine = import_module(settings.SESSION_ENGINE)
        self.SessionStore = engine.SessionStore

    def process_request(self, request: HttpRequest):
        session_key = request.COOKIES.get(settings.SESSION_COOKIE_NAME)
        request.session = self.SessionStore(session_key)

        # Set user agent and IP in the session store
        client_ip = ServerUtils.get_request_ip(request)
        user_agent = ServerUtils.user_agent(request)
        request.session.user_agent = user_agent
        request.session.user_ip = client_ip

        response = self.get_response(request)
        return response

    def process_response(self, request, response):
        """
        If request.session was modified, or if the configuration is to save the
        session every time, save the changes and set a session cookie or delete
        the session cookie if the session has been emptied.
        """
        try:
            accessed = request.session.accessed
            modified = request.session.modified
            empty = request.session.is_empty()
        except AttributeError:
            pass
        else:
            # First check if we need to delete this cookie.
            # The session should be deleted only if the session is entirely empty
            if settings.SESSION_COOKIE_NAME in request.COOKIES and empty:
                response.delete_cookie(
                    settings.SESSION_COOKIE_NAME,
                    path=settings.SESSION_COOKIE_PATH,
                    domain=settings.SESSION_COOKIE_DOMAIN,
                    samesite=settings.SESSION_COOKIE_SAMESITE,
                )
            else:
                if accessed:
                    patch_vary_headers(response, ("Cookie",))
                if (modified or settings.SESSION_SAVE_EVERY_REQUEST) and not empty:
                    if request.session.get_expire_at_browser_close():
                        max_age = None
                        expires = None
                    else:
                        max_age = request.session.get_expiry_age()
                        expires_time = time.time() + max_age
                        expires = http_date(expires_time)
                    # Save the session data and refresh the client cookie.
                    # Skip session save for 500 responses, refs #3881.
                    if response.status_code != 500:
                        try:
                            request.session.save()
                        except UpdateError:
                            raise SuspiciousOperation(
                                "The request's session was deleted before the "
                                "request completed. The user may have logged "
                                "out in a concurrent request, for example."
                            )
                        response.set_cookie(
                            settings.SESSION_COOKIE_NAME,
                            request.session.session_key,
                            max_age=max_age,
                            expires=expires,
                            domain=settings.SESSION_COOKIE_DOMAIN,
                            path=settings.SESSION_COOKIE_PATH,
                            secure=settings.SESSION_COOKIE_SECURE or None,
                            httponly=settings.SESSION_COOKIE_HTTPONLY or None,
                            samesite=settings.SESSION_COOKIE_SAMESITE,
                        )
        return response
