import re
from typing import Optional, Callable
from check_proxy_without_requests import *

# Define the regex pattern to match IP:PORT[@username:password]
pattern = re.compile(r"(\d+\.\d+\.\d+\.\d+):(\d+)(?:@(\w+):(\w+))?")


class ParseResult:
    def __init__(
        self, ip: str, port: str, username: Optional[str], password: Optional[str]
    ):
        self.ip = ip
        self.port = port
        self.username = username
        self.password = password


def parse_line(line: str) -> Optional[ParseResult]:
    """
    Parse a line to extract IP, port, username, and password.

    Args:
        line: A string containing IP:PORT[@username:password].

    Returns:
        A ParseResult object containing IP, port, username (if present), and password (if present).
    """
    match = pattern.search(line)
    if match:
        ip = match.group(1)
        port = match.group(2)
        username = match.group(3)
        password = match.group(4)
        return ParseResult(ip, port, username, password)
    else:
        return None


def read_file_and_parse(filename: str, callback: Callable[[ParseResult], None]) -> None:
    """
    Read a file and parse each line to extract IP, port, username, and password.

    Args:
        filename: The name of the file to read.
        callback: A callback function to be called with each parsed result.
    """
    with open(filename, "r", encoding="utf-8") as file:
        for line in file:
            line = line.strip()
            if not line:  # Skip empty lines
                continue
            parsed_data = parse_line(line)
            if parsed_data:
                callback(parsed_data)


def test_proxy(proxy_info: ParseResult) -> None:
    """
    Test the proxy connection using the provided proxy information.

    Args:
        proxy_info: A ParseResult object containing proxy information (IP, port, username, password).
    """
    # Here, you can implement the logic to test the proxy connection
    # print(f"Testing proxy: {proxy_info.ip}:{proxy_info.port} - Username: {proxy_info.username}, Password: {proxy_info.password}")
    proxy = f"{proxy_info.ip}:{proxy_info.port}"
    working = False
    if check_http_proxy(proxy):
        print(f"{proxy} HTTP Proxy is working!")
        working = True

    if check_socks5_proxy(proxy):
        print(f"{proxy} SOCKS5 Proxy is working!")
        working = True

    if check_socks4_proxy(proxy):
        print(f"{proxy} SOCKS4 Proxy is working!")
        working = True

    if not working:
        print(f"{proxy} not working")


if __name__ == "__main__":
    filename = "proxies.txt"  # Replace 'file.txt' with the actual filename
    read_file_and_parse(filename, test_proxy)
