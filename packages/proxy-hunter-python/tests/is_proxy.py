from proxy_hunter.curl import build_request, is_prox


def test_proxy(proxy):
    endpoint = "https://ip-get-geolocation.com/api/json"
    proxy_types = ["http", "socks4", "socks5"]

    for proxy_type in proxy_types:
        try:
            response = build_request(
                proxy=proxy,
                proxy_type=proxy_type,
                method="GET",
                post_data=None,
                endpoint=endpoint,
            )
            # Return the first successful response
            return response
        except Exception:
            # Handle or log the exception if needed
            continue

    return None


if __name__ == "__main__":
    proxy = "91.192.33.52:43801"
    print("is_prox", "success" if isinstance(is_prox(proxy, True), str) else "failed")

    response = test_proxy(proxy)
    print(response.json() if response else f"{proxy} is failed")
