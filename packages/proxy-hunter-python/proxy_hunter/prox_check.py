""" Proxy server checker for proxyscan.py """

from typing import Optional
import requests


def is_prox(proxy_server: str, debug: bool = False) -> Optional[str]:
    """
    Test if a given proxy server is working by sending a request to a test site.

    Args:
        proxy_server (str): The proxy server to test.

    Returns:
        Optional[str]: The proxy server if it is working, otherwise None.
    """
    proxyDict = {"http": proxy_server, "https": proxy_server, "socks": proxy_server}

    test_site = "http://api.ipify.org/?format=json"
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5 (.NET CLR 3.5.30729)"
    }

    for proxy_type, proxy in proxyDict.items():
        proxies = {proxy_type: proxy}
        try:
            response = requests.get(
                test_site, headers=headers, proxies=proxies, verify=False
            )
            if response.ok:
                status = response.status_code
                if status == 200:
                    return proxy
                else:
                    print(f"{proxy} got status {status}")
            else:
                print(f"{proxy} response not ok")
        except Exception as e:
            if debug:
                print(f"{proxy} error {e}")

    return None


if __name__ == "__main__":
    result = is_prox("51.15.190.163:14522", True)
    print(result)
