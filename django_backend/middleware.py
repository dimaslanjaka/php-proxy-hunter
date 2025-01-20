import hashlib
import hmac
import os
import sys
from typing import Set
from urllib.parse import parse_qs, urlparse, urlunparse

from django.conf import settings
from django.contrib.auth.hashers import BasePasswordHasher
from django.http import HttpResponse
from django.middleware.csrf import CsrfViewMiddleware
from django.utils.crypto import constant_time_compare, pbkdf2
from django.utils.deprecation import MiddlewareMixin
from filelock import FileLock
from proxy_hunter import is_valid_ip

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


class CustomCsrfExemptMiddleware(MiddlewareMixin):
    def process_request(self, request):
        if request.headers.get("X-Greasemonkey-Script"):
            request.csrf_processing_done = True


class CsrfExemptCsrfViewMiddleware(CsrfViewMiddleware):
    def process_view(self, request, callback, callback_args, callback_kwargs):
        if request.headers.get("X-Greasemonkey-Script"):
            return None
        return super().process_view(request, callback, callback_args, callback_kwargs)


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
    initial_url = ["https://sh.webmanajemen.com/proxyManager.html"]
    lock_file = os.path.join(settings.BASE_DIR, "sitemap.lock")

    def process_response(self, request, response):
        # Check if it's a GET request and the response status is 200
        # and the response content type is html
        if (
            request.method == "GET"
            and response.status_code == 200
            and response.get("Content-Type", "").startswith("text/html")
        ):
            # Get the current URL with query parameters
            url = self.get_full_url(request)
            # Append to sitemap.txt
            self.append_to_sitemap(url)
        return response

    def get_full_url(self, request) -> str:
        # Get scheme and host from the request
        scheme = request.scheme
        host = request.get_host()

        # Determine if port should be included
        host_parts = host.split(":")
        if len(host_parts) == 2:
            host_with_port = f"{host_parts[0]}:{host_parts[1]}"
        else:
            if scheme == "https" and host in settings.ALLOWED_HOSTS:
                prod_port = int(settings.PRODUCTION_PORT)
                if prod_port not in (80, 443) and prod_port > 443:
                    host_with_port = f"{host}:{prod_port}"
                else:
                    host_with_port = host
            else:
                host_with_port = host

        # Get the full path including query parameters
        path = request.get_full_path()

        # Construct the full URL
        result = urlunparse((scheme, host_with_port, path, "", "", ""))
        return result

    def is_valid_url(self, url: str) -> bool:
        """Check if the URL is valid."""
        parsed = urlparse(url)

        # Check if the URL has a valid scheme and netloc
        valid_schemes = {"http", "https"}
        return (
            parsed.scheme in valid_schemes
            and bool(parsed.netloc)
            and bool(parsed.path)  # Ensure there's a path, which is usually required
        )

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
        sitemap_path = os.path.join(settings.BASE_DIR, "sitemap.txt")

        # Use file locking to prevent concurrent access
        lock = FileLock(self.lock_file, timeout=10)  # Timeout is optional
        with lock:
            # Read the current contents of the file
            existing_urls: Set[str] = set(self.initial_url)
            if os.path.exists(sitemap_path):
                with open(sitemap_path, "r", encoding="utf-8") as file:
                    for line in file:
                        if line.strip() and line.strip() not in existing_urls:
                            existing_urls.add(line)

            # Add the new URL if it's not already in the set
            if url not in existing_urls:
                existing_urls.add(url)

            # Write the updated URLs to the file
            with open(sitemap_path, "w", encoding="utf-8") as file:
                for line in sorted(existing_urls):
                    line = line.strip()
                    if self.is_valid_url(line):
                        p = urlparse(line)
                        # Skip indexing IP hostname
                        if not is_valid_ip(p.hostname):
                            file.write(line.strip() + "\n")


# your_app/middleware/cors.py


class SimpleCORSHeadersMiddleware:
    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        response = self.get_response(request)

        # Add the CORS headers to allow all origins
        response["Access-Control-Allow-Origin"] = "*"
        response["Access-Control-Allow-Methods"] = (
            "GET, POST, PUT, PATCH, DELETE, OPTIONS"
        )
        response["Access-Control-Allow-Headers"] = "Content-Type, Authorization"

        # Handle preflight (OPTIONS) requests
        if request.method == "OPTIONS":
            response.status_code = 200

        return response


class MD5PasswordHasher(BasePasswordHasher):
    """MD5 with secret key"""

    algorithm = "mwsk"

    def salt(self):
        """Return a salt for the password (in this case, SECRET_KEY)."""
        return settings.SECRET_KEY

    def encode(self, password, salt=None):
        if salt is None:
            salt = self.salt()
        hash = hashlib.md5((salt + password).encode()).hexdigest()
        return f"{self.algorithm}${salt}${hash}"

    def verify(self, password, encoded):
        algorithm, salt, hash = encoded.split("$", 2)
        encoded_2 = self.encode(password, salt)
        return constant_time_compare(encoded, encoded_2)

    def safe_summary(self, encoded):
        """Provide a safe representation of the hash (e.g., for logging)."""
        algorithm, salt, hash = encoded.split("$", 2)
        return {
            "algorithm": algorithm,
            "salt": salt,
            "hash": hash[:6] + "..." + hash[-6:],
        }
