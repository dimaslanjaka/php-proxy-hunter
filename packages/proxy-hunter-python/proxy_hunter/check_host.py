import socket
from typing import Union


def check_host(host: str, port: Union[int, str], timeout: float = 2.0) -> bool:
    """
    Check whether a TCP connection can be established to a host and port.

    This performs a simple TCP connect (SYN) with a timeout and does not
    send any application-level data.

    :param host: IP address or domain name (e.g. "8.8.8.8" or "google.com")
    :param port: TCP port number (int or numeric string, e.g. 80 or "80")
    :param timeout: Connection timeout in seconds
    :return: True if connection succeeds, False otherwise
    """
    try:
        port_int = int(port)
        with socket.create_connection((host, port_int), timeout=timeout):
            return True
    except (ValueError, OSError):
        return False


if __name__ == "__main__":
    print(check_host("8.8.8.8", 53))
    print(check_host("google.com", "80"))
    print(check_host("localhost", "notaport"))  # False
