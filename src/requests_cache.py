import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
import hashlib
import json
import time
from typing import Optional

import requests
from requests.exceptions import RequestException

from src.func import get_relative_path
from src.func_certificate import output_pem

CACHE_DIR = get_relative_path(".cache")
os.makedirs(CACHE_DIR, 777, True)
CACHE_EXPIRY = 7 * 24 * 60 * 60  # 1 week in seconds


def get_cache_file_path(url: str) -> str:
    """Generate a file path for caching based on the MD5 hash of the URL."""
    md5_hash = hashlib.md5(url.encode("utf-8")).hexdigest()
    return os.path.join(CACHE_DIR, f"{md5_hash}.json")


def cache_response(url: str, response: requests.Response) -> None:
    """Save the response content to a file."""
    os.makedirs(CACHE_DIR, exist_ok=True)
    cache_file_path = get_cache_file_path(url)

    cache_data = {
        "timestamp": time.time(),
        "status_code": response.status_code,
        "headers": dict(response.headers),
        "content": response.content.decode("utf-8"),  # Use content for binary data
    }

    with open(cache_file_path, "w") as file:
        json.dump(cache_data, file)


def load_cached_response(url: str) -> Optional[requests.Response]:
    """Load the response content from a cache file if it exists and is still valid."""
    cache_file_path = get_cache_file_path(url)

    if not os.path.exists(cache_file_path):
        return None

    with open(cache_file_path, "r") as file:
        cache_data = json.load(file)

    # Check if the cache has expired
    if time.time() - cache_data["timestamp"] > CACHE_EXPIRY:
        os.remove(cache_file_path)
        return None

    return MockResponse(cache_data)


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


def get_with_proxy(
    url,
    proxy_type: Optional[str] = "http",
    proxy_raw: Optional[str] = None,
    timeout=10,
    debug: Optional[bool] = False,
):
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
    # Check if we have a cached response
    cached_response = load_cached_response(url)
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

    try:
        if proxies:
            response = requests.get(
                url, proxies=proxies, timeout=timeout, verify=output_pem
            )
        else:
            response = requests.get(url, timeout=timeout, verify=output_pem)

        response.raise_for_status()
        cache_response(url, response)
        return response

    except RequestException as e:
        if debug:
            print(f"Request Error: {e}")
    return None


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
    response = get_with_proxy("https://bing.com")
    print(response.headers)
