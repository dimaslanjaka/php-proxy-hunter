import os
import sys

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import build_request

for protocol in ["http", "socks4", "socks5"]:
    try:
        proxy = "34.92.250.88:11111"
        res = build_request(proxy, protocol)
        print(f"{protocol}://{proxy}", "working")
    except Exception as e:
        print(f"failed check {protocol}://{proxy}", e)
