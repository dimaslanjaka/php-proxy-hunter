from pprint import pprint
import sys
import os

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_proxy import *
from src.func import *
from proxy_hunter import build_request

proxy = "3.10.93.50:3128"


def perform_check(proxy, protocol, url):
    return check_proxy(proxy, protocol, url)


url = "http://httpbin.org/ip"
protocols = ["http", "socks4", "socks5"]

# Using ThreadPoolExecutor to run checks in parallel
with ThreadPoolExecutor() as executor:
    futures = {
        executor.submit(perform_check, proxy, protocol, url): protocol
        for protocol in protocols
    }
    results = {}
    for future in as_completed(futures):
        protocol = futures[future]
        try:
            result = future.result()
            results[protocol] = result
        except Exception as exc:
            results[protocol] = str(exc)

working = False

for protocol, result in results.items():
    if result.result == True:
        print(f"{proxy} working {protocol.upper()}")
        working = True

if not working:
    pprint([vars(result) for result in results.values()])

# cookie initializer
endpoints = [
    "https://bing.com",
    "https://www.axis.co.id/",
    "https://axis-1945.s3-ap-southeast-1.amazonaws.com",
    "https://trxmultipayments.api.axis.co.id",
    "https://google.com",
    "https://github.com",
    "https://myim3app.indosatooredoo.com/#/login",
    "https://digilife.ioh.co.id/marketplace/home",
    "http://httpforever.com/",
    "https://ioh.co.id/portal/id/iohindex",
    "http://httpbin.org/ip",
]
for endpoint in endpoints:
    output = build_request(endpoint=endpoint)
    print(output.cookies)
    time.sleep(1)
