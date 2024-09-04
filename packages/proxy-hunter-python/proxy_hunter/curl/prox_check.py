""" Proxy server checker for proxyscan.py """

from typing import Optional

from proxy_hunter.curl.request_helper import build_request


def is_prox(proxy_server: str, debug: bool = False) -> Optional[str]:
    """
    Test if a given proxy server is working by sending a request to a test site.

    Args:
        proxy_server (str): The proxy server to test.

    Returns:
        Optional[str]: The proxy server if it is working, otherwise None.
    """
    endpoint = "http://api.ipify.org/?format=json"
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5 (.NET CLR 3.5.30729)"
    }

    proxy_types = ["http", "socks4", "socks5"]

    for proxy_type in proxy_types:
        format_proxy = f"{proxy_type}://{proxy_server}"
        try:
            response = build_request(
                proxy=proxy_server,
                proxy_type=proxy_type,
                method="GET",
                post_data=None,
                endpoint=endpoint,
                headers=headers,
            )
            if response and response.ok:
                response_json = response.json()
                if response_json.get("ip"):
                    return proxy_server
                elif debug:
                    print(f"{format_proxy} failed: caused by decode json")
        except Exception as e:
            if debug:
                print(f"{format_proxy} failed:", str(e))

    return None


if __name__ == "__main__":
    result = is_prox("51.15.190.163:14522", True)
    print(result)
