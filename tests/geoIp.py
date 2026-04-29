import os
import random
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import get_device_ip, Proxy
from src.geoPlugin import get_locale_from_country_code, get_with_proxy, get_geo_ip
from proxy_hunter import read_file
from src.ProxyDB import ProxyDB
from bs4 import BeautifulSoup

if __name__ == "__main__":
    country_code = "ID"
    language_country = get_locale_from_country_code(country_code)
    print(f"The language for country code {country_code} is {language_country}.")
    db = ProxyDB()
    proxies = db.extract_proxies(read_file("proxies.txt"))
    if not proxies:
        proxies = Proxy.from_list(db.get_working_proxies())
    random.shuffle(proxies)
    is_break = False

    for item in proxies:
        proxy = item.proxy
        print(f"Testing proxy: {proxy}")

        geoIp = get_geo_ip(proxy)
        if geoIp is not None:
            print(geoIp.to_json())
            break
