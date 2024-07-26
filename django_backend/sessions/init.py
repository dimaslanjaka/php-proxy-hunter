import logging
import time
from importlib import import_module
from django.conf import settings
from django.contrib.sessions.backends.base import UpdateError
from .SessionStore import SessionStore as FileSessionStore
from django.core.exceptions import SuspiciousOperation
from django.http import HttpRequest, HttpResponse
from django.utils.cache import patch_vary_headers
from django.utils.deprecation import MiddlewareMixin
from django.utils.http import http_date
from django_backend.ServerUtils import ServerUtils
from django_backend.sessions.session_encryption import session_encryption

encryptor = session_encryption()


class SessionStore(FileSessionStore):
    def create(self):
        # Generate a custom session key
        self._session_key = self._generate_custom_session_key()
        self.save(must_create=True)

    def _generate_custom_session_key(self):
        global encryptor
        _session_unique_key = getattr(self, "_session_unique_key", "")

        # Generate MD5 hash of the unique string
        return encryptor.encrypt(_session_unique_key)


logger = logging.getLogger(__name__)


class SessionMiddleware(MiddlewareMixin):
    def __init__(self, get_response=None):
        self.get_response = get_response
        self.SessionStore = FileSessionStore
        engine = import_module(settings.SESSION_ENGINE)
        self.SessionStore = engine.SessionStore

    def process_request(self, request: HttpRequest):
        session_key = request.COOKIES.get(settings.SESSION_COOKIE_NAME)
        request.session = self.SessionStore(session_key)

        # Set user agent and IP in the session store
        client_ip = ServerUtils.get_request_ip(request)
        user_agent = ServerUtils.user_agent(request)
        request.session._session_unique_key = f"{user_agent}-{client_ip}"

        response = self.get_response(request)
        return response

    def process_response(self, request: HttpRequest, response: HttpResponse):
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
