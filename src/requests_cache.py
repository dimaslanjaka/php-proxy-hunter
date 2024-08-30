from http.cookiejar import Cookie, MozillaCookieJar
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
import hashlib
import json
import time
from typing import List, Optional

import requests
from requests.exceptions import RequestException

from src.func import delete_path, get_relative_path, resolve_folder
from src.func_certificate import output_pem

CACHE_DIR = get_relative_path("tmp/requests_cache")
resolve_folder(CACHE_DIR)
CACHE_EXPIRY = 7 * 24 * 60 * 60  # 1 week in seconds


def get_cache_file_path(url: str) -> str:
    """Generate a file path for caching based on the MD5 hash of the URL."""
    md5_hash = hashlib.md5(url.encode("utf-8")).hexdigest()
    return os.path.join(CACHE_DIR, f"{md5_hash}.json")


def cache_response(
    url: str, response: requests.Response, cache_file_path: Optional[str] = None
) -> None:
    """Save the response content to a file."""
    os.makedirs(CACHE_DIR, exist_ok=True)
    cache_file_path = (
        get_cache_file_path(url) if not cache_file_path else cache_file_path
    )

    cache_data = {
        "timestamp": time.time(),
        "status_code": response.status_code,
        "headers": dict(response.headers),
        "content": response.content.decode("utf-8"),  # Use content for binary data
    }

    with open(cache_file_path, "w", encoding="utf-8") as file:
        json.dump(cache_data, file)


def load_cached_response(
    url: str,
    cache_file_path: Optional[str] = None,
    expiration: Optional[int] = CACHE_EXPIRY,
) -> Optional[requests.Response]:
    """
    Load the response content from a cache file if it exists and is still valid.

    Args:
        url (str): The URL for which the cache is being accessed.
        cache_file_path (Optional[str]): The path to the cache file. If None, a default path is used.
        expiration (Optional[int]): The cache expiration time in seconds. If None, default CACHE_EXPIRY is used.

    Returns:
        Optional[requests.Response]: A MockResponse object with the cached data, or None if no valid cache is found.
    """
    cache_file_path = cache_file_path or get_cache_file_path(url)

    if not os.path.exists(cache_file_path):
        return None

    with open(cache_file_path, "r", encoding="utf-8") as file:
        cache_data = json.load(file)

    expiration = expiration or CACHE_EXPIRY

    # Check if the cache has expired
    if time.time() - cache_data.get("timestamp", 0) > expiration:
        os.remove(cache_file_path)
        return None

    return MockResponse(cache_data)


def delete_cached_response(url: str):
    """delete saved response"""
    cache_file_path = get_cache_file_path(url)
    delete_path(cache_file_path)


class MockResponse(requests.Response):
    def __init__(self, data):
        super().__init__()
        self.status_code = data["status_code"]
        self.headers = data["headers"]
        self._content = data["content"].encode("utf-8")  # Convert content back to bytes

    @property
    def text(self):
        return self._content.decode("utf-8")

    @property
    def content(self):
        return self._content

    @property
    def ok(self):
        return 200 <= self.status_code < 300

    def raise_for_status(self):
        if not self.ok:
            raise RequestException(f"HTTP error: {self.status_code}")

    def json(self):
        try:
            return json.loads(self.text)
        except json.JSONDecodeError:
            return {}


session = requests.Session()


def get_with_proxy(
    url,
    proxy_type: Optional[str] = "http",
    proxy_raw: Optional[str] = None,
    timeout=10,
    debug: Optional[bool] = False,
    no_cache: Optional[bool] = False,
    cache_file_path: Optional[str] = None,
):
    global session
    """
    Perform a GET request using a proxy of the specified type and cache the response.

    Parameters:
    - url (str): The URL to perform the GET request on.
    - proxy_type (str): The type of the proxy. Possible values: 'http', 'socks4', 'socks5', 'https'.
    - proxy_raw (str): The URL of the proxy to use (e.g., 'http://username:password@proxy_ip:proxy_port').
    - timeout (int): Timeout for the request in seconds (default is 10).
    - debug (bool): Whether to print debug information.

    Returns:
    - response (requests.Response): The response object returned by requests.get().
    """
    if not no_cache:
        # Check if we have a cached response
        cached_response = load_cached_response(url, cache_file_path)
        if cached_response:
            return cached_response

    proxies = None
    if proxy_raw:
        split = proxy_raw.split("@")
        proxy = split[0]
        auth = None
        if len(split) > 1:
            auth = split[1]
        if proxy_type == "socks4":
            proxies = {"http": f"socks4://{proxy}", "https": f"socks4://{proxy}"}
        elif proxy_type == "socks5":
            proxies = {"http": f"socks5://{proxy}", "https": f"socks5://{proxy}"}
        else:
            proxies = {"http": proxy, "https": proxy}

    cookie_file = get_relative_path("tmp/cookies/requests_cache.txt")
    os.makedirs(os.path.dirname(cookie_file), 777, True)

    cookie_header = """# Netscape HTTP Cookie File
# http://curl.haxx.se/rfc/cookie_spec.html
# This is a generated file!  Do not edit.
"""

    def reset_cookie():
        if not os.path.exists(cookie_file):
            with open(cookie_file, "w", encoding="utf-8") as file:
                file.write(cookie_header)

    try:
        reset_cookie()
        cookie_jar = MozillaCookieJar(cookie_file)
        cookie_jar.load(filename=cookie_file, ignore_discard=True, ignore_expires=True)
        session.cookies.update(cookie_jar)
    except Exception as e:
        if debug:
            print(f"fail load cookie jar {e}")
        if "invalid" in str(e).lower():
            reset_cookie()
            cookie_jar = MozillaCookieJar(cookie_file)

    default_headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/81.0.4044.138 Safari/537.36",
        "Accept-Language": "en-US,en",
        "Cache-Control": "no-cache",
        "Pragma": "no-cache",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.5",
    }
    session.headers.update(default_headers)

    try:
        if proxies:
            response = session.get(url, proxies=proxies, timeout=timeout, verify=False)
        else:
            response = session.get(url, timeout=timeout, verify=False)

        # response.raise_for_status()

        # save cookie
        cookies_to_be_saved = [cookie_header]
        update_cookie_jar(cookie_jar, response.cookies)
        for cookie in cookie_jar:
            if isinstance(cookie_jar, MozillaCookieJar):
                domain = cookie.domain
                secure = "TRUE" if cookie.secure else "FALSE"
                initial_dot = "TRUE" if domain.startswith(".") else "FALSE"
                expires = str(cookie.expires) if cookie.expires is not None else ""
                name = "" if cookie.value is None else cookie.name
                value = cookie.value if cookie.value is not None else cookie.name
                cookie_raw = "\t".join(
                    [domain, initial_dot, cookie.path, secure, expires, name, value]
                )
            cookies_to_be_saved.append(cookie_raw)
        cookie_built = "\n".join(cookies_to_be_saved + [""])
        with open(cookie_file, "w", encoding="utf-8") as file:
            file.write(cookie_built)

        if not no_cache:
            cache_response(url, response, cache_file_path)
        return response

    except RequestException as e:
        if debug:
            print(f"Request Error: {e}")
    return None


def update_cookie_jar(cookie_jar: MozillaCookieJar, cookies: List[Cookie]):
    cookie_dict = {cookie.name: cookie for cookie in cookie_jar}

    for cookie in cookies:
        if cookie.name in cookie_dict:
            # If cookie exists, update its value and expiration date
            cookie_dict[cookie.name].value = cookie.value
            cookie_dict[cookie.name].expires = cookie.expires
            cookie_dict[cookie.name].secure = cookie.secure

            # MozillaCookieJar specific attributes
            cookie_dict[cookie.name].path = cookie.path
            cookie_dict[cookie.name].domain = cookie.domain

            # Check if the cookie has 'rest' attribute
            if hasattr(cookie, "rest"):
                cookie_dict[cookie.name].rest = cookie.rest

        else:
            # If cookie does not exist, add it to the cookie jar
            cookie_jar.set_cookie(cookie)


def clear_cache(url: Optional[str] = None) -> None:
    """Remove cache files. If URL is provided, remove cache for that URL only; otherwise, clear all cache files."""
    if url:
        cache_file_path = get_cache_file_path(url)
        if os.path.exists(cache_file_path):
            os.remove(cache_file_path)
    else:
        if os.path.exists(CACHE_DIR):
            for filename in os.listdir(CACHE_DIR):
                file_path = os.path.join(CACHE_DIR, filename)
                if os.path.isfile(file_path):
                    os.remove(file_path)


if __name__ == "__main__":
    get_with_proxy(
        "http://httpbin.org/cookies/set/sessioncookie/123456789",
        no_cache=True,
        debug=True,
    )
    get_with_proxy("http://httpbin.org/cookies", no_cache=True, debug=True)
    get_with_proxy("https://bing.com", debug=True, no_cache=True)
