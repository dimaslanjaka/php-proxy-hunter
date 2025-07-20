import os
import random
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import get_device_ip
from src.geoPlugin import get_locale_from_country_code, get_with_proxy
from proxy_hunter import read_file
from src.ProxyDB import ProxyDB
from bs4 import BeautifulSoup

if __name__ == "__main__":
    country_code = "ID"
    language_country = get_locale_from_country_code(country_code)
    print(f"The language for country code {country_code} is {language_country}.")
    db = ProxyDB()
    proxies = db.extract_proxies(read_file("proxies.txt"))
    random.shuffle(proxies)
    is_break = False

    for item in proxies:
        proxy = item.proxy
        # geoIp = get_geo_ip(proxy)
        # print(json.dumps(geoIp))

        # geoIp = get_geo_ip2(proxy)
        # if geoIp is not None:
        #     print(geoIp.to_json())

        url = "https://sh.webmanajemen.com/data/azenv.php"
        device_ip = get_device_ip()

        for protocol in ["http", "socks5", "socks4"]:
            # proxy_url = f'{proxy}@user:pass'
            response = get_with_proxy(url, protocol, proxy)
            if response and response.ok:
                # Parse the HTML content using BeautifulSoup
                soup = BeautifulSoup(response.text, "html.parser")

                # Find all <pre> tags
                pre_tags = soup.find_all("pre")

                # Extract and print the content inside each <pre> tag
                for pre_tag in pre_tags:
                    print(pre_tag.get_text())
                is_break = True

        if is_break:
            break
