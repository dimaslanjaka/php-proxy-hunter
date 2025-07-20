import socket
import socks
import urllib.request

# gulp nuitka --py tests/check_proxy_without_requests.py


def check_http_proxy(proxy):
    try:
        proto_proxy = {"http": f"http://{proxy}"}
        proxy_handler = urllib.request.ProxyHandler(proto_proxy)
        opener = urllib.request.build_opener(proxy_handler)
        urllib.request.install_opener(opener)
        response = urllib.request.urlopen("http://httpbin.org/ip", timeout=10)
        if response.status == 200:
            return True
    except Exception as e:
        print(str(e))
    return False


def check_socks5_proxy(proxy):
    ip, port = proxy.split(":")
    try:
        socks.setdefaultproxy(socks.PROXY_TYPE_SOCKS5, ip, int(port))
        socket.socket = socks.socksocket
        socket.socket(socket.AF_INET, socket.SOCK_STREAM).connect(("httpbin.org", 80))
        return True
    except Exception as e:
        print(str(e))
    return False


def check_socks4_proxy(proxy):
    ip, port = proxy.split(":")
    try:
        socks.setdefaultproxy(socks.PROXY_TYPE_SOCKS4, ip, int(port))
        socket.socket = socks.socksocket
        socket.socket(socket.AF_INET, socket.SOCK_STREAM).connect(("httpbin.org", 80))
        return True
    except Exception as e:
        print(str(e))
    return False


if __name__ == "__main__":
    # proxy = input("Enter proxy in IP:PORT format: ")
    proxy = "114.132.202.78:8080"

    if check_http_proxy(proxy):
        print("HTTP Proxy is working!")

    if check_socks5_proxy(proxy):
        print("SOCKS5 Proxy is working!")

    if check_socks4_proxy(proxy):
        print("SOCKS4 Proxy is working!")
