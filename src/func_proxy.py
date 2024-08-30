import datetime
import http.client as http_client
import logging
import os
import sys
from proxy_hunter import Proxy

from src.func_platform import is_debug, is_django_environment

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
import random
import re
import socket
import ssl
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from http.cookiejar import Cookie, LWPCookieJar, MozillaCookieJar
from typing import Any, Callable, Dict, List, Optional, Union
from urllib.parse import urlparse

import requests
import urllib3
from proxy_checker import ProxyChecker
from requests.adapters import HTTPAdapter
import http.cookiejar as cookiejar
from src.func import (
    debug_log,
    file_append_str,
    file_remove_empty_lines,
    find_substring_from_regex,
    get_relative_path,
    get_unique_dicts_by_key_in_list,
    is_date_rfc3339_hour_more_than,
    read_file,
    move_string_between,
    write_file,
)
from src.func_certificate import output_pem
from src.func_console import get_caller_info, green, log_proxy, red
from src.func_useragent import get_pc_useragent
from src.ProxyDB import ProxyDB

# Set the certificate file in environment variables
os.environ["REQUESTS_CA_BUNDLE"] = output_pem
os.environ["SSL_CERT_FILE"] = output_pem

# Replace create default https context method
ssl._create_default_https_context = lambda: ssl.create_default_context(
    cafile=output_pem
)

# Suppress InsecureRequestWarning
requests.packages.urllib3.disable_warnings()
urllib3.disable_warnings()


def requests_enable_verbose():
    """
    Enable verbose logging for debugging HTTP requests.
    """
    http_client.HTTPConnection.debuglevel = 1
    logging.basicConfig()
    logging.getLogger().setLevel(logging.DEBUG)
    requests_log = logging.getLogger("requests.packages.urllib3")
    requests_log.setLevel(logging.DEBUG)
    requests_log.propagate = True


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
) -> requests.Response:
    """
    Builds and sends an HTTP request using the provided settings.

    Args:
        proxy (str, optional): The proxy address in the format IP:PORT.
        proxy_type (str, optional): Type of proxy ('http', 'socks4', or 'socks5').
        method (str, optional): HTTP method ('GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', or 'PATCH'). Defaults to 'GET'.
        post_data (Dict[str, str], optional): Data to send in a POST, PUT, or PATCH request. Defaults to None.
        endpoint (str, optional): The endpoint URL for the request. Defaults to 'https://bing.com'.
        headers (Dict[str, str], optional): Headers for the request. Defaults to None.
        no_cache (bool, optional): Flag to bypass cache by appending a unique query parameter. Defaults to False.
        cookie_file (str, optional): Path to the cookie file. Defaults to 'tmp/cookies/default.txt'.
        session (requests.Session, optional): An existing session to reuse. If None, a new session is created.
        keep_headers (bool, optional): Flag to determine if default headers should be overridden by provided headers. Defaults to None.

    Returns:
        requests.Response: The response object from the HTTP request.

    Raises:
        ValueError: If an invalid proxy type is specified or if an unsupported HTTP method is used.
    """
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
    if cookie_file is not None:
        cookie_str = ""
        cookie_jar = None
        cookie_header = None
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
            "Accept-Language": "en-US,en",
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
                endpoint, data=post_data, timeout=10, verify=False
            )
        else:
            response: requests.Response = request_methods[method_upper](
                endpoint, timeout=10, verify=False
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
    cookie_jar: Union[MozillaCookieJar, LWPCookieJar], cookies: List[Cookie]
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


HEADER_JOIN_ESCAPE_RE = re.compile(r"([\"\\])")


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


def get_device_ip() -> Union[None, str]:
    ip_services = [
        "https://api64.ipify.org",
        "https://ipinfo.io/ip",
        "https://api.myip.com",
        "https://ip.42.pl/raw",
        "https://ifconfig.me/ip",
        "https://cloudflare.com/cdn-cgi/trace",
        "https://httpbin.org/ip",
        "https://api.ipify.org",
    ]
    for url in ip_services:
        response = build_request(endpoint=url)
        if response and response.ok:
            # parse IP using regex
            ip_address_match = re.search(
                r"(?!0)(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)",
                response.text,
            )
            if ip_address_match:
                return ip_address_match.group(0)
    return None


class ProxyCheckResult:
    def __init__(
        self,
        result: bool,
        latency: Union[float, int],
        error: str,
        status: Union[int, None],
        private: bool,
        response: requests.Response = None,
        proxy: str = None,
        type: str = None,
        url: str = None,
        https: bool = None,
        additional: Dict[str, Any] = {},
    ):
        self.result = result
        self.latency = latency
        self.error = error
        self.status = status
        self.private = private
        self.certificate = output_pem
        self.response = response
        self.proxy = proxy
        self.type = type
        self.https = https
        self.url = url
        self.additional = additional

    def __str__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"

    def __repr__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"


def get_requests_error(
    e: Union[Dict, Exception], return_default_error_message: bool = True
) -> Optional[str]:
    """
    Parses and extracts error information from requests-related exceptions.

    Args:
        e (Union[Dict, Exception]): The exception object or dictionary containing error information.
        return_default_error_message (bool, optional): Flag indicating whether to return the default error message
            if no specific error is detected. Defaults to True.

    Returns:
        Optional[str]: A descriptive error message extracted from the exception, or None if no error is detected
            and return_default_error_message is False.
    """

    get_socks = find_substring_from_regex(
        r"SOCKS\d proxy server sent invalid data", str(e)
    )
    proxy_timed_out = find_substring_from_regex(
        r"\b(?:\d{1,3}\.){3}\d{1,3}\b timed out", str(e)
    )
    domain_timed_out = find_substring_from_regex(r"\b(?:\w+\.)+\w+\b timed out", str(e))
    connection_refused = find_substring_from_regex(
        r"the target machine actively refused it", str(e)
    )
    connection_read_timeout = find_substring_from_regex(r"Read timed out", str(e))
    connection_closed = find_substring_from_regex(
        r"Connection closed unexpectedly", str(e)
    )
    connection_remote_closed = find_substring_from_regex(
        r"connection was forcibly closed by the remote host", str(e)
    )
    socks_credential_missing = find_substring_from_regex(
        r"SOCKS\d authentication methods were rejected", str(e)
    )
    connection_proxy_failed = find_substring_from_regex(
        r"Cannot connect to proxy", str(e)
    )
    connection_aborted = find_substring_from_regex(
        r"Connection aborted.*BadStatusLine", str(e)
    )
    if get_socks:
        error = get_socks
    elif socks_credential_missing:
        error = f"{socks_credential_missing} (Private)"
    elif connection_aborted:
        error = "ERR_CONNECTION_ABORTED"
    elif connection_refused:
        error = "ERR_CONNECTION_REFUSED"
    elif connection_proxy_failed:
        error = "ERR_PROXY_CONNECTION_FAILED"
    elif connection_closed:
        error = "ERR_CONNECTION_CLOSED"
    elif connection_remote_closed:
        error = "ERR_CONNECTION_CLOSED by remote host"
    elif domain_timed_out:
        error = domain_timed_out
    elif connection_read_timeout:
        error = connection_read_timeout
    elif proxy_timed_out:
        error = proxy_timed_out
    elif return_default_error_message:
        error = str(e)
    else:
        error = None
    return error


def check_proxy(
    proxy: str,
    proxy_type: str,
    endpoint: str = None,
    headers: Dict[str, str] = None,
    callback: Callable[[ProxyCheckResult], None] = None,
) -> ProxyCheckResult:
    """
    Checks if the provided proxy is working by sending a request.

    Args:
        proxy (str): The proxy address in the format IP:PORT.
        proxy_type (str): Type of proxy ('http', 'socks4', or 'socks5').
        callback (Callable[[ProxyCheckResult], None]): Callback function to execute before returning the result.
        endpoint (str, optional): The endpoint URL for the request. Defaults to None.
        headers (Dict[str, str], optional): Headers for the request. Defaults to None.

    Returns:
        ProxyCheckResult: An object containing the result of the check.
    """
    default_headers = {"User-Agent": get_pc_useragent()}
    if headers is not None:
        default_headers.update(headers)
    endpoint = endpoint or "https://httpbin.org/headers"
    latency = -1
    result = False
    is_private = False
    status = None
    error = None
    response = None

    if is_port_open(proxy):
        try:
            response = build_request(
                proxy,
                proxy_type,
                method="GET",
                endpoint=endpoint,
                headers=default_headers,
            )
            latency = response.elapsed.total_seconds() * 1000  # in milliseconds
            is_private = (
                "X-Forwarded-For:" in response.headers
                or "Proxy-Authorization:" in response.headers
            )
            status = response.status_code
            valid_status_codes = [200, 201, 202, 204, 301, 302, 304]
            result = response.status_code in valid_status_codes
        except Exception as e:
            error = get_requests_error(e)
            pass
    else:
        proxy_ip, proxy_port = proxy.split(":")
        error = f"{proxy_ip} port {proxy_port} closed"

    if response is not None:
        current = urlparse(response.url)
        original = urlparse(endpoint)
        if current.hostname != original.hostname:
            # the proxy is private
            result = False
            is_private = True

    if not result:
        try:
            checker = ProxyChecker()
            lib_result = checker.check_proxy(proxy)
            if lib_result and proxy_type in lib_result["protocols"]:
                result = True
        except Exception:
            pass

    c_result = ProxyCheckResult(
        result=result,
        latency=latency,
        error=error,
        status=status,
        private=is_private,
        response=response,
        proxy=proxy,
        type=proxy_type,
    )

    # Execute the callback before returning the result
    if callback is not None and callable(callback):
        callback(c_result)

    return c_result


def parse_ip_port(line: str) -> tuple[Optional[str], Optional[str]]:
    """
    Parse an IP:PORT pair from a string.

    Args:
        line (str): The string containing the IP:PORT pair.

    Returns:
        Tuple[str, str]: A tuple containing the IP address and port.
    """
    pattern = r"(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d{1,5})"
    match = re.search(pattern, line)
    if match:
        ip = match.group(1)
        port = match.group(2)
        return ip, port
    else:
        return None, None


def is_port_open(address: str) -> bool:
    """
    Check if a port is open on a given IP address and port number.

    Args:
        address (str): A string in the format "IP:PORT".

    Returns:
        bool: True if the port is open, False otherwise.
    """
    result = False
    try:
        # Split the address into IP and port
        host, port = address.split(":")
        port = int(port)  # Convert port to an integer
        # Create a new socket
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        # Set a timeout to prevent hanging
        s.settimeout(10)
        # Try to connect to the host and port
        s.connect((host, port))
        # debug_log(f"{address} {green('port open')}")
        # If successful, the port is open
        result = True
    except Exception as e:
        # log_proxy(f"{address} {red('port closed')} {str(e)}")
        result = False
    finally:
        # Close the socket
        s.close()
    return result


def upload_proxy(proxy: Any) -> None:
    """
    Uploads a proxy to a specific URL.

    Args:
        proxy (str): The proxy to upload.

    Returns:
        None: No return value.
    """
    if not is_debug():
        # production mode
        if is_django_environment():
            # skip called by django
            return
    if not isinstance(proxy, str):
        proxy = str(proxy)
    if len(proxy.strip()) > 10:
        cookies = {
            "__ga": "GA1.2.1234567890.1234567890",
            "_ga": "GA1.3.9876543210.9876543210",
        }
        response = send_post(
            url="https://sh.webmanajemen.com/proxyAdd.php",
            data={"proxies": proxy},
            cookies=cookies,
        )
        debug_log(f"{proxy} uploaded -> {response}".strip())
        time.sleep(1)


def check_proxy_new(proxy: str):
    db = ProxyDB()
    logfile = get_relative_path("proxyChecker.txt")
    status = None
    working = False
    protocols = []
    print(f"check_proxy_new -> {proxy}")
    if not is_port_open(proxy):
        log_proxy(f"{proxy} {red('port closed')}")
        status = "port-closed"
    else:
        # Define a function to handle check_proxy with the correct arguments
        def handle_check(proxy, protocol, url):
            return check_proxy(proxy, protocol, url)

        # Create a ThreadPoolExecutor
        with ThreadPoolExecutor(max_workers=3) as executor:
            # Submit the tasks
            checks = [
                executor.submit(handle_check, proxy, "http", "http://httpbin.org/ip"),
                executor.submit(handle_check, proxy, "socks4", "http://httpbin.org/ip"),
                executor.submit(handle_check, proxy, "socks5", "http://httpbin.org/ip"),
            ]

            # Iterate through the completed tasks
            for i, future in enumerate(as_completed(checks)):
                protocol = ["HTTP", "SOCKS4", "SOCKS5"][i]
                check = future.result()

                if check.result:
                    log = f"> {proxy} ✓ {protocol}"
                    protocols.append(protocol.lower())
                    file_append_str(logfile, log)
                    print(green(log))
                    working = True
                else:
                    log = f"> {proxy} 🗙 {protocol}"
                    file_append_str(logfile, f"{log} -> {check.error}")
                    print(f"{red(log)} -> {check.error}")
                    working = False

            if not working:
                status = "dead"
            else:
                status = "active"
                upload_proxy(proxy)

    if db is not None and status is not None:
        data = {"status": status}
        if len(protocols) > 0:
            data["type"] = "-".join(protocols).upper()
        db.update_data(proxy, data)

    move_string_between(
        get_relative_path("proxies.txt"), get_relative_path("dead.txt"), proxy
    )
    file_remove_empty_lines(logfile)


def get_proxies(
    working_only: Optional[bool] = False, untested_only: Optional[bool] = False
) -> List[Dict[str, str]]:
    """
    get proxies without dead proxies
    """
    proxies: List[Dict[str, str]] = []
    db = ProxyDB(get_relative_path("src/database.sqlite"))

    if not working_only or untested_only:
        proxies.extend(db.db.select("proxies", "*", "status = ?", ["untested"]))
        # proxies = list(filter(lambda proxy: is_proxy_recently_checked(proxy), proxies))
    if not untested_only or working_only:
        proxies.extend(db.db.select("proxies", "*", "status = ?", ["active"]))

    if not working_only or not untested_only:

        def parse(item: Proxy):
            proxies.append(item.to_dict())

        proxies_file = get_relative_path("proxies.txt")
        if os.path.exists(proxies_file):
            db.from_file(proxies_file, parse, 100)

    # Filter proxies
    proxies = list(filter(lambda proxy: not is_private_or_dead(proxy), proxies))
    proxies = get_unique_dicts_by_key_in_list(proxies, "proxy")

    # close database
    db.close()

    if not proxies:
        log_proxy("proxies empty")
        file, line = get_caller_info()
        debug_log(f"Called from file '{file}', line {line}")
        return []

    random.shuffle(proxies)
    return proxies


def is_proxy_recently_checked(proxy: Dict[str, Union[None, str]]) -> bool:
    if proxy["last_check"] is None:
        return True
    return is_date_rfc3339_hour_more_than(proxy["last_check"], 24)


def is_private_or_dead(proxy: Dict[str, str]) -> bool:
    return proxy.get("private") == "true" or proxy.get("status") in (
        "port-closed",
        "dead",
    )


def check_all_proxies(count: int = 10):
    proxies = get_proxies()
    # log_proxy(f"Total untested proxies ({len(proxies)})")

    # Filter out invalid proxies
    valid_proxies = [
        item["proxy"]
        for item in proxies[:count]
        if item and item["proxy"].strip() and len(item["proxy"].strip()) >= 10
    ]

    with ThreadPoolExecutor() as executor:
        # Map each proxy to check_proxy_new function
        executor.map(check_proxy_new, valid_proxies)


def is_post_length_within_limit(data_string: str, limit_mb: float = 8.0) -> bool:
    """
    Check if the length of the given string when encoded in UTF-8 is within the specified limit in megabytes.

    Args:
        data_string (str): The string whose length needs to be checked.
        limit_mb (float, optional): The maximum allowed size in megabytes. Defaults to 8.0.

    Returns:
        bool: True if the length is within the limit, False otherwise.
    """
    # Convert string to bytes
    data_bytes = data_string.encode("utf-8")

    # Calculate the size in MB
    size_mb = len(data_bytes) / (1024 * 1024)  # 1 MB = 1024 * 1024 bytes

    # Check if the size is within the limit
    return size_mb <= limit_mb


def truncate_string_size(data_string: str, max_size_mb: float = 2.0) -> str:
    """
    Truncate the given string to a maximum size in megabytes if its size exceeds the specified limit.

    Args:
        data_string (str): The string to be truncated.
        max_size_mb (float, optional): The maximum allowed size in megabytes. Defaults to 2.0.

    Returns:
        str: The truncated string.
    """
    # Convert string to bytes
    data_bytes = data_string.encode("utf-8")

    # Calculate the maximum number of bytes allowed for the specified size in MB
    max_bytes = int(max_size_mb * 1024 * 1024)

    # Check if size exceeds the specified limit
    if len(data_bytes) > max_bytes:
        # Truncate the string
        truncated_bytes = data_bytes[:max_bytes]

        # Decode the truncated bytes back to string
        truncated_string = truncated_bytes.decode("utf-8")

        return truncated_string

    return data_string


def send_post(
    url: str,
    data: Dict[str, Union[str, int]],
    cookies: Optional[Dict[str, str]] = None,
    headers: Optional[Dict[str, str]] = None,
) -> Union[str, None]:
    """
    Make a POST request with SSL verification.

    Args:
        url (str): The URL to which the POST request will be sent.
        data (Dict[str, Union[str, int]]): The data to be sent with the POST request.
        cookies (Dict[str, str], optional): Dictionary of cookies to attach to the request. Default is None.
        headers (Dict[str, str], optional): Dictionary of HTTP headers to attach to the request. Default is None.

    Returns:
        Union[str, None]: The response text if the request is successful, otherwise None.
    """
    default_headers = {"User-Agent": get_pc_useragent()}
    if headers is not None:
        default_headers.update(headers)
    try:
        session = requests.Session()
        response = session.post(
            url=url, data=data, cookies=cookies, headers=default_headers
        )
        if response.status_code == 200:
            return response.text
        else:
            return f"Error: {response.status_code} - {response.text}"
    except Exception as e:
        return f"Error: {str(e)}"
