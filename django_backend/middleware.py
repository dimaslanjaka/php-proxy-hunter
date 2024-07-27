import os
import shutil
import sys
from urllib.parse import parse_qs, urlparse, urlunparse

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

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


class FaviconMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        if request.path == "/favicon.ico":
            favicon_path = os.path.join(settings.BASE_DIR, "public", "favicon.ico")
            if os.path.exists(favicon_path):
                with open(favicon_path, "rb") as f:
                    return HttpResponse(f.read(), content_type="image/x-icon")
        return self.get_response(request)


class SitemapMiddleware(MiddlewareMixin):
    ignore_paths = ["/proxy/check", "/proxy/filter", "/sitemap", "/rss", "/atom"]

    def process_response(self, request, response):
        # Check if it's a GET request and the response status is 200
        if request.method == "GET" and response.status_code == 200:
            # Get the current URL with query parameters
            url = self.get_full_url(request)
            # Append to sitemap.txt
            self.append_to_sitemap(url)
        return response

    def get_full_url(self, request):
        # Construct the full URL including scheme and host
        scheme = request.scheme
        host = request.get_host()
        path = request.get_full_path()
        return urlunparse((scheme, host, path, "", "", ""))

    def merge_and_deduplicate_sitemaps(self, file1, file2, output_file):
        # Initialize a set to store unique lines
        unique_lines = set()

        # Read lines from the first file if it exists
        if os.path.exists(file1):
            with open(file1, "r") as f1:
                for line in f1:
                    unique_lines.add(line.strip())

        # Read lines from the second file if it exists
        if os.path.exists(file2):
            with open(file2, "r") as f2:
                for line in f2:
                    unique_lines.add(line.strip())

        # Write unique lines to the output file
        with open(output_file, "w") as f_out:
            for line in sorted(unique_lines):
                f_out.write(f"{line}\n")

    def append_to_sitemap(self, url):
        # Parse the URL and check if it contains the `date` query parameter
        parsed_url = urlparse(url)
        query_params = parse_qs(parsed_url.query)
        if "date" in query_params:
            return  # Ignore URLs with `date` query parameter

        # Check if the URL path is in the ignore_paths list
        path = parsed_url.path
        if any(path.startswith(ignore_path) for ignore_path in self.ignore_paths):
            return  # Ignore URLs matching ignore_paths

        # Define the path to sitemap.txt
        sitemap_path = os.path.join(
            settings.BASE_DIR, "public", "static", "sitemap.txt"
        )

        # Ensure the directory exists
        sitemap_dir = os.path.dirname(sitemap_path)
        if not os.path.exists(sitemap_dir):
            os.makedirs(sitemap_dir)  # Create the directory if it doesn't exist

        # Read the current contents of the file
        existing_urls = set()
        if os.path.exists(sitemap_path):
            with open(sitemap_path, "r") as file:
                existing_urls = set(line.strip() for line in file if line.strip())

        # Add the new URL if it's not already in the set
        if url.strip() and url not in existing_urls:
            existing_urls.add(url.strip())

        # Write the updated URLs to the file
        with open(sitemap_path, "w") as file:
            for line in sorted(existing_urls):
                file.write(line + "\n")

        # Merge and deduplicate sitemaps
        self.merge_and_deduplicate_sitemaps(
            sitemap_path,
            os.path.join(settings.BASE_DIR, "sitemap.txt"),
            os.path.join(settings.BASE_DIR, "sitemap.txt"),
        )
