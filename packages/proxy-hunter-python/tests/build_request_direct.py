import os
import sys
import requests
import re

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import build_request, get_device_ip

device_ip = get_device_ip()
print(f"Device IP: {device_ip}")

# Shared (hardcoded) proxy value
proxy = "http://qtculbqe:iazrxzml7g27@31.59.20.176:6754"

# Endpoints useful for verifying proxy behavior
endpoints = [
    "https://httpbin.org/ip",
    "https://api.ipify.org?format=json",
    "https://icanhazip.com",
    "https://checkip.amazonaws.com",
    "https://api.ipify.org?format=json",
]


if __name__ == "__main__":
    for ep in endpoints:
        print(f"==> Testing endpoint: {ep}")
        try:
            response = build_request(
                proxy=proxy, method="GET", endpoint=ep, verify=True, timeout=15
            )
            print("Status Code:", response.status_code)
            text = response.text if hasattr(response, "text") else response.content
            # Ensure we have a str for safe slicing and concatenation
            if isinstance(text, bytes):
                text = text.decode("utf-8", errors="replace")
            display = text if len(text) <= 500 else text[:500] + "..."
            print("Content:", display)
            # Extract first IPv4 from response and validate against device IP
            m = re.search(r"(\d{1,3}(?:\.\d{1,3}){3})", text)
            resp_ip = m.group(1) if m else None
            if device_ip and resp_ip:
                if resp_ip == device_ip:
                    print(
                        "Validation: response IP matches device IP -> proxy NOT applied"
                    )
                else:
                    print(
                        "Validation: response IP differs from device IP -> proxy applied"
                    )
            else:
                print(
                    "Validation: could not extract IP from response or device IP unknown"
                )
        except requests.RequestException as e:
            print("Request failed:", e)
        print()
