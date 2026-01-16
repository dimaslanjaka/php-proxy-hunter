import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import extract_proxies, is_valid_proxy, is_valid_hostname

print(f"Is valid proxy: {is_valid_proxy('dc.oxylabs.io:8000')}")
print(f"Is valid hostname: {is_valid_hostname('dc.google.io')}")

proxy_str = f"""another long string proxy_user:proxy_password@dc.oxylabs.io:8000 another long string
wgbfrmqf:lynb55lcsui6@173.0.9.209:5792
custom_proxy: http://dimaslanjaka_JD93N:myProxyCredentials=008@dc.oxylabs.io:8000
"""
print(f"Extracting proxy: {proxy_str}")
result = extract_proxies(proxy_str)
print(f"Extracted proxies: {len(result)}")
for p in result:
    print(f"  Proxy: {p.proxy}")
    print(f"  Username: {p.username}")
    print(f"  Password: {p.password}")
    print("-----")
