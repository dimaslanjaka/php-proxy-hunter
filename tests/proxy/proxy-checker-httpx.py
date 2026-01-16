import asyncio
import json
import os
import sys

from proxy_hunter import extract_proxies

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../..")))

from artisan.proxy_checker_httpx import ProxyTestResult, test_proxy

proxy_password = "myProxyCredentials=008"
proxy_user = "dimaslanjaka_JD93N"
# usage: curl -x dc.oxylabs.io:8000 -U "user-dimaslanjaka_JD93N-country-US:myProxyCredentials=008" https://ip.oxylabs.io/location

proxy_str = f"http://{proxy_user}:{proxy_password}@dc.oxylabs.io:8000"
# proxy_str = f"http://wgbfrmqf:lynb55lcsui6@173.0.9.209:5792		"
print(f"Testing proxy: {proxy_str}")
extract = extract_proxies(proxy_str)
if not extract:
    print("No valid proxy extracted")
    sys.exit(0)
print(f"Extracted proxies: ")
print(f"  Proxy: {extract[0].proxy}")
print(f"  Username: {extract[0].username}")
print(f"  Password: {extract[0].password}")


async def main():
    res = await test_proxy(proxy_str)
    print("Test result:")
    for proto, info in res.items():
        print(f"{proto.upper():8} {json.dumps(info.to_dict(), indent=2)}")
    print("Summary:")
    print(f"  Working protocols: {[p for p, i in res.items() if i.ok]}")
    print(f"  SSL supported: {[p for p, i in res.items() if i.ssl]}")
    working = {p: i for p, i in res.items() if i.ok}
    if not working:
        print("  Proxy failed for all protocols")
    else:
        print("  Proxy is working")


asyncio.run(main())
