import datetime
import os
import re
import ssl
import certifi
import time
from http import cookiejar as cookiejar
from http.cookiejar import Cookie, LWPCookieJar, MozillaCookieJar
from typing import Dict, List, Optional, Union

import requests
import urllib3
from requests.adapters import HTTPAdapter
from requests.cookies import RequestsCookieJar

from proxy_hunter.utils import read_file, write_file

# Set the certificate file in environment variables
os.environ["REQUESTS_CA_BUNDLE"] = certifi.where()
os.environ["SSL_CERT_FILE"] = certifi.where()

# Replace create default https context method
ssl._create_default_https_context = lambda: ssl.create_default_context(
    cafile=certifi.where()
)

# Suppress InsecureRequestWarning
requests.packages.urllib3.disable_warnings()
urllib3.disable_warnings()


def build_request(
    proxy: Optional[str] = None,
    proxy_type: Optional[str] = None,
    method: str = "GET",
    post_data: Optional[Dict[str, str]] = None,
    endpoint: str = "https://bing.com",
    headers: Optional[Dict[str, str]] = None,
    no_cache: Optional[bool] = False,
    cookie_file: Optional[str] = "tmp/cookies/default.txt",
    session: Optional[requests.Session] = None,
    keep_headers: Optional[bool] = None,
    **kwargs,
) -> requests.Response:
    """
    Builds and sends an HTTP request using the provided settings.

    Args:
        proxy (Optional[str]): The proxy address in the format IP:PORT. Defaults to None.
        proxy_type (Optional[str]): Type of proxy ('http', 'socks4', or 'socks5'). Defaults to None.
        method (str): HTTP method ('GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'). Defaults to 'GET'.
        post_data (Optional[Dict[str, str]]): Data to send in a POST, PUT, or PATCH request. Defaults to None.
        endpoint (str): The endpoint URL for the request. Defaults to 'https://bing.com'.
        headers (Optional[Dict[str, str]]): Headers for the request. Defaults to None.
        no_cache (Optional[bool]): Flag to bypass cache by appending a unique query parameter. Defaults to False.
        cookie_file (Optional[str]): Path to the cookie file. Defaults to 'tmp/cookies/default.txt'.
        session (Optional[requests.Session]): An existing session to reuse. If None, a new session is created.
        keep_headers (Optional[bool]): Flag to determine if default headers should be overridden by provided headers. Defaults to None.
        **kwargs: Additional keyword arguments to pass to the requests method.

    Returns:
        requests.Response: The response object from the HTTP request.

    Raises:
        ValueError: If an invalid proxy type is specified or if an unsupported HTTP method is used.
    """
    verify_certificate = kwargs.pop("verify", False)
    # Create a new session if one is not provided
    if session is None:
        session = requests.Session()
    session.mount("https://", HTTPAdapter(max_retries=3))

    if no_cache:
        # Append a unique query parameter to bypass caches
        unique_param = f"nocache={int(time.time())}"
        if "?" in endpoint:
            endpoint += "&" + unique_param
        else:
            endpoint += "?" + unique_param

    if proxy_type is not None and proxy is not None:
        if proxy_type.lower() == "http":
            session.proxies = {"http": proxy, "https": proxy}
        elif proxy_type.lower() == "socks4":
            session.proxies = {
                "http": f"socks4://{proxy}",
                "https": f"socks4://{proxy}",
            }
        elif proxy_type.lower() == "socks5":
            session.proxies = {
                "http": f"socks5://{proxy}",
                "https": f"socks5://{proxy}",
            }
        else:
            raise ValueError(
                "Invalid proxy type. Supported types are 'http', 'socks4', and 'socks5'."
            )

    # Load cookies from the specified cookie file
    cookie_jar = None
    cookie_header = None
    if cookie_file is not None:
        cookie_str = ""
        if not os.path.exists(cookie_file):
            generate_netscape_cookie_jar(cookie_file)
        if os.path.exists(cookie_file):
            cookie_str = read_file(cookie_file)
        if cookie_str != "":
            try:
                if "Netscape HTTP Cookie File" in cookie_str:
                    cookie_jar = MozillaCookieJar(cookie_file)
                    cookie_header = """# Netscape HTTP Cookie File
# http://curl.haxx.se/rfc/cookie_spec.html
# This is a generated file!  Do not edit.
"""
                else:
                    cookie_jar = LWPCookieJar(cookie_file)
                    cookie_header = "#LWP-Cookies-2.0"

                cookie_jar.load(
                    ignore_discard=True, ignore_expires=True, filename=cookie_file
                )
                session.cookies.update(cookie_jar)
            except Exception as e:
                print(f"Error loading cookies from file: {e}")
                # Skip using cookies if loading fails
                cookie_jar = None
                cookie_header = None

    # Setup browser headers
    if headers is None:
        headers = {}
    if not keep_headers:
        default_headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) "
            "Chrome/81.0.4044.138 Safari/537.36",
            "Cache-Control": "no-cache",
            "Pragma": "no-cache",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.5",
        }
        default_headers.update(headers)
        session.headers.update(default_headers)
    else:
        session.headers.update(headers)

    # Setup request method
    request_methods = {
        "POST": session.post,
        "PUT": session.put,
        "GET": session.get,
        "DELETE": session.delete,
        "HEAD": session.head,
        "OPTIONS": session.options,
        "PATCH": session.patch,
    }
    method_upper = method.upper()
    if method_upper in request_methods:
        if method_upper in ["POST", "PUT", "PATCH"]:
            response: requests.Response = request_methods[method_upper](
                endpoint,
                data=post_data,
                timeout=10,
                verify=verify_certificate,
                **kwargs,
            )
        else:
            response: requests.Response = request_methods[method_upper](
                endpoint, timeout=10, verify=verify_certificate, **kwargs
            )
    else:
        raise ValueError(f"Unsupported method: {method}")

    # Save cookies back to file
    if cookie_jar is not None and cookie_header is not None:
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
            else:
                cookie_raw = "Set-Cookie3: %s" % lwp_cookie_str(cookie)
            cookies_to_be_saved.append(cookie_raw)
        write_file(cookie_file, "\n".join(cookies_to_be_saved + [""]))
        time.sleep(1)

    return response


def generate_netscape_cookie_jar(file_path):
    if not os.path.exists(file_path):
        write_file(
            file_path,
            """# Netscape HTTP Cookie File
# http://curl.haxx.se/rfc/cookie_spec.html
# This is a generated file!  Do not edit.
""",
        )
    # Create a MozillaCookieJar instance
    cookie_jar = cookiejar.MozillaCookieJar(file_path)

    # Example: Add some cookies to the jar
    cookie1 = cookiejar.Cookie(
        version=0,
        name="session_id",
        value="12345",
        port=None,
        port_specified=False,
        domain="example.com",
        domain_specified=False,
        domain_initial_dot=False,
        path="/",
        path_specified=True,
        secure=False,
        expires=None,
        discard=True,
        comment=None,
        comment_url=None,
        rest={"HttpOnly": None},  # Optional: Set non-standard attribute
        rfc2109=False,
    )
    cookie_jar.set_cookie(cookie1)

    cookie2 = cookiejar.Cookie(
        version=0,
        name="user_id",
        value="67890",
        port=None,
        port_specified=False,
        domain="example.com",
        domain_specified=False,
        domain_initial_dot=False,
        path="/",
        path_specified=True,
        secure=False,
        expires=None,
        discard=True,
        comment=None,
        comment_url=None,
        rest={"HttpOnly": None},  # Optional: Set non-standard attribute
        rfc2109=False,
    )
    cookie_jar.set_cookie(cookie2)

    # Save cookies to file
    cookie_jar.save(ignore_discard=True, ignore_expires=True)


def update_cookie_jar(
    cookie_jar: Union[MozillaCookieJar, LWPCookieJar],
    cookies: Union[List[Cookie], RequestsCookieJar],
):
    cookie_dict = {cookie.name: cookie for cookie in cookie_jar}

    for cookie in cookies:
        if cookie.name in cookie_dict:
            # If cookie exists, update its value and expiration date
            cookie_dict[cookie.name].value = cookie.value
            cookie_dict[cookie.name].expires = cookie.expires
            cookie_dict[cookie.name].secure = cookie.secure

            if isinstance(cookie_jar, LWPCookieJar):
                # LWPCookieJar specific attributes
                cookie_dict[cookie.name].path_specified = cookie.path_specified
                cookie_dict[cookie.name].domain_specified = cookie.domain_specified
            else:
                # MozillaCookieJar specific attributes
                cookie_dict[cookie.name].path = cookie.path
                cookie_dict[cookie.name].domain = cookie.domain

                # Check if the cookie has 'rest' attribute
                if hasattr(cookie, "rest"):
                    cookie_dict[cookie.name].rest = cookie.rest

        else:
            # If cookie does not exist, add it to the cookie jar
            cookie_jar.set_cookie(cookie)


def join_header_words(lists):
    """Do the inverse (almost) of the conversion done by split_header_words.

    Takes a list of lists of (key, value) pairs and produces a single header
    value.  Attribute values are quoted if needed.

    >>> join_header_words([[("text/plain", None), ("charset", "iso-8859-1")]])
    'text/plain; charset="iso-8859-1"'
    >>> join_header_words([[("text/plain", None)], [("charset", "iso-8859-1")]])
    'text/plain, charset="iso-8859-1"'

    """
    headers = []
    for pairs in lists:
        attr = []
        for k, v in pairs:
            if v is not None:
                if not re.search(r"^\w+$", v):
                    v = HEADER_JOIN_ESCAPE_RE.sub(r"\\\1", v)  # escape " and \
                    v = '"%s"' % v
                k = "%s=%s" % (k, v)
            attr.append(k)
        if attr:
            headers.append("; ".join(attr))
    return ", ".join(headers)


def lwp_cookie_str(cookie: Cookie):
    """Return string representation of Cookie in the LWP cookie file format.

    Actually, the format is extended a bit -- see module docstring.

    """
    h = [(cookie.name, cookie.value), ("path", cookie.path), ("domain", cookie.domain)]
    if cookie.port is not None:
        h.append(("port", cookie.port))
    if cookie.path_specified:
        h.append(("path_spec", None))
    if cookie.port_specified:
        h.append(("port_spec", None))
    if cookie.domain_initial_dot:
        h.append(("domain_dot", None))
    if cookie.secure:
        h.append(("secure", None))
    if cookie.expires:
        h.append(("expires", time2isoz(float(cookie.expires))))
    if cookie.discard:
        h.append(("discard", None))
    if cookie.comment:
        h.append(("comment", cookie.comment))
    if cookie.comment_url:
        h.append(("commenturl", cookie.comment_url))

    keys = sorted(cookie._rest.keys())
    for k in keys:
        h.append((k, str(cookie._rest[k])))

    h.append(("version", str(cookie.version)))

    return join_header_words([h])


HEADER_JOIN_ESCAPE_RE = re.compile(r"([\"\\])")


def time2isoz(t=None):
    """Return a string representing time in seconds since epoch, t.

    If the function is called without an argument, it will use the current
    time.

    The format of the returned string is like "YYYY-MM-DD hh:mm:ssZ",
    representing Universal Time (UTC, aka GMT).  An example of this format is:

    1994-11-24 08:49:37Z

    """
    if t is None:
        dt = datetime.datetime.utcnow()
    else:
        dt = datetime.datetime.utcfromtimestamp(t)
    return "%04d-%02d-%02d %02d:%02d:%02dZ" % (
        dt.year,
        dt.month,
        dt.day,
        dt.hour,
        dt.minute,
        dt.second,
    )
