import re
from django.utils.deprecation import MiddlewareMixin
from django.http import HttpRequest, HttpResponse
from django.conf import settings
from htmlmin.minify import html_minify


class MinifyHTMLMiddleware(MiddlewareMixin):
    def can_minify_response(self, request: HttpRequest, response: HttpResponse):
        result = "text/html" in response.get("Content-Type", "")

        if hasattr(settings, "EXCLUDE_FROM_MINIFYING"):
            for url_pattern in settings.EXCLUDE_FROM_MINIFYING:
                regex = re.compile(url_pattern)
                if regex.match(request.path.lstrip("/")):
                    result = False
                    break

        return result

    def process_response(self, request: HttpRequest, response: HttpResponse):
        # enable_minify = getattr(settings, "HTML_MINIFY", not settings.DEBUG)
        keep_comments = getattr(settings, "KEEP_COMMENTS_ON_MINIFYING", False)
        parser = getattr(settings, "HTML_MIN_PARSER", "html5lib")
        allowed = self.can_minify_response(request, response)  # and enable_minify
        if allowed:
            response.content = html_minify(
                response.content, ignore_comments=not keep_comments, parser=parser
            )
            response["Content-Length"] = len(response.content)
        return response
