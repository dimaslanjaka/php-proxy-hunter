import re

from django.conf import settings
from django.core.cache import cache
from django.http import HttpRequest, HttpResponse
from django.middleware.csrf import CsrfViewMiddleware
from django.utils.deprecation import MiddlewareMixin
from htmlmin.minify import html_minify


class CustomCsrfExemptMiddleware(MiddlewareMixin):
    def process_request(self, request):
        if request.headers.get("X-Greasemonkey-Script"):
            request.csrf_processing_done = True


class CsrfExemptCsrfViewMiddleware(CsrfViewMiddleware):
    def process_view(self, request, callback, callback_args, callback_kwargs):
        if request.headers.get("X-Greasemonkey-Script"):
            return None
        return super().process_view(request, callback, callback_args, callback_kwargs)


class MinifyHTMLMiddleware(MiddlewareMixin):
    def can_minify_response(self, request: HttpRequest, response: HttpResponse) -> bool:
        result = "text/html" in response.get("Content-Type", "")

        if hasattr(settings, "EXCLUDE_FROM_MINIFYING"):
            for url_pattern in settings.EXCLUDE_FROM_MINIFYING:
                regex = re.compile(url_pattern)
                if regex.match(request.path.lstrip("/")):
                    result = False
                    break

        return result

    def process_response(
        self, request: HttpRequest, response: HttpResponse
    ) -> HttpResponse:
        keep_comments = getattr(settings, "KEEP_COMMENTS_ON_MINIFYING", False)
        parser = getattr(settings, "HTML_MIN_PARSER", "html5lib")
        allowed = self.can_minify_response(request, response)

        cache_key = f"minified_{request.get_full_path()}"
        cached_response = cache.get(cache_key)

        if cached_response and not settings.DEBUG:
            # return cache only for production mode
            response.content = cached_response
        elif allowed and not settings.DEBUG:
            minified_content = html_minify(
                response.content, ignore_comments=not keep_comments, parser=parser
            )
            response.content = minified_content
            response["Content-Length"] = len(response.content)
            cache.set(
                cache_key, minified_content, timeout=60 * 15
            )  # Cache for 15 minutes

        return response
