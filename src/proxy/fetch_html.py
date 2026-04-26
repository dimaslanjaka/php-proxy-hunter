import concurrent.futures
from typing import Iterable

from proxy_hunter import build_request, extract_proxies, check_proxy


def check_proxy_variants(proxy) -> str:
    """Check proxy in an order depending on port.

    If the proxy port is 1080, prefer SOCKS5 -> SOCKS4 -> HTTP.
    Otherwise use HTTP -> SOCKS4 -> SOCKS5.
    Return one of: "HTTP", "SOCKS4", "SOCKS5", or "NONE".
    """
    # Attempt to detect port if proxy is a string like "host:port" or similar
    port = None
    try:
        proxy_str = str(proxy)
        if ":" in proxy_str:
            maybe_port = proxy_str.rsplit(":", 1)[1]
            port = int(maybe_port)
    except Exception:
        port = None

    if port == 1080:
        check_order = ["socks5", "socks4", "http"]
    else:
        check_order = ["http", "socks4", "socks5"]

    labels = {"http": "HTTP", "socks4": "SOCKS4", "socks5": "SOCKS5"}
    for proto in check_order:
        result = check_proxy(proxy, proto)
        if getattr(result, "result", False):
            return labels.get(proto, proto.upper())
    return "NONE"


def check_one(proxy_details) -> str:
    print(f"Checking proxy: {proxy_details.proxy}")
    p = proxy_details.proxy
    res = check_proxy_variants(p)
    if res == "NONE":
        return f"{p} is not working for HTTP, SOCKS4, or SOCKS5."
    return f"{p} is working for {res}."


def check_proxies_concurrent(proxies: Iterable, workers: int = 4) -> None:
    with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as ex:
        futures = [ex.submit(check_one, pd) for pd in proxies]
        for fut in concurrent.futures.as_completed(futures):
            try:
                print(fut.result())
            except Exception as e:
                print("Error checking proxy:", e)


def main(url: str, workers: int = 4) -> None:
    request = build_request(endpoint=url, no_cache=True)
    if not getattr(request, "ok", False):
        status = getattr(request, "status_code", "unknown")
        print(f"Failed to fetch {url}: status={status}")
        return
    fetched_proxies = extract_proxies(request.text)
    check_proxies_concurrent(fetched_proxies, workers=workers)


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Fetch and check proxies concurrently")
    parser.add_argument(
        "--workers",
        type=int,
        default=4,
        help="Number of concurrent workers (default: 4)",
    )
    parser.add_argument(
        "--url",
        default="https://free-proxy-list.net/en/ssl-proxy.html",
        help="URL to fetch proxies from",
    )
    # Allow unknown args so external wrappers can pass extra flags
    args = parser.parse_known_args()[0]
    main(args.url, workers=args.workers)
