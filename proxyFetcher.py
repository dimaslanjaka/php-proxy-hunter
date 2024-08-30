from proxy_hunter import *
from src.func import *
from src.func_proxy import *
from src.requests_cache import get_with_proxy
from datetime import datetime as dt

urls = [
    "https://github.com/zloi-user/hideip.me/blob/main/connect.txt",
    "https://proxies.lat/proxy.txt",
    "https://api.openproxylist.xyz/http.txt",
    "https://api.openproxylist.xyz/socks5.txt",
    "https://api.openproxylist.xyz/socks4.txt",
    "http://alexa.lr2b.com/proxylist.txt",
    "https://multiproxy.org/txt_all/proxy.txt",
    "https://multiproxy.org/txt_anon/proxy.txt",
    "https://proxyspace.pro/http.txt",
    "https://proxyspace.pro/https.txt",
    "http://rootjazz.com/proxies/proxies.txt",
    "https://api.proxyscrape.com/v3/free-proxy-list/get?request=displayproxies&proxy_format=protocolipport&format=text",
    "https://api.proxyscrape.com/v3/free-proxy-list/get?request=displayproxies&proxy_format=protocolipport&format=text&timeout=20000",
    "https://cyber-hub.pw/statics/proxy.txt",
    "https://github.com/ALIILAPRO/Proxy/raw/main/http.txt",
    "https://github.com/ALIILAPRO/Proxy/raw/main/socks4.txt",
    "https://github.com/ALIILAPRO/Proxy/raw/main/socks5.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/http_proxies.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/https_proxies.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/proxies_dump.json",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/socks4_proxies.txt",
    "https://github.com/Anonym0usWork1221/Free-Proxies/raw/main/proxy_files/socks5_proxies.txt",
    "https://github.com/elliottophellia/yakumo/raw/master/results/mix_checked.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/http/http.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/https/https.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/socks4/socks4.txt",
    "https://github.com/officialputuid/KangProxy/raw/KangProxy/socks5/socks5.txt",
    "https://github.com/prxchk/proxy-list/raw/main/all.txt",
    "https://github.com/proxifly/free-proxy-list/blob/main/proxies/all/data.txt",
    "https://github.com/roosterkid/openproxylist/blob/main/HTTPS_RAW.txt",
    "https://github.com/roosterkid/openproxylist/blob/main/SOCKS4_RAW.txt",
    "https://github.com/roosterkid/openproxylist/blob/main/SOCKS5_RAW.txt",
    "https://github.com/roosterkid/openproxylist/main/HTTPS_RAW.txt",
    "https://github.com/roosterkid/openproxylist/main/SOCKS4_RAW.txt",
    "https://github.com/roosterkid/openproxylist/main/SOCKS5_RAW.txt",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/http.txt",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks4.txt",
    "https://raw.githubusercontent.com/TheSpeedX/SOCKS-List/master/socks5.txt",
    "https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt",
    "https://raw.githubusercontent.com/hendrikbgr/Free-Proxy-Repo/master/proxy_list.txt",
    "https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt",
    "https://raw.githubusercontent.com/mertguvencli/http-proxy-list/main/proxy-list/data.txt",
    "https://raw.githubusercontent.com/mmpx12/proxy-list/master/http.txt",
    "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt",
    "https://raw.githubusercontent.com/proxy4parsing/proxy-list/main/http.txt",
    "https://raw.githubusercontent.com/RX4096/proxy-list/main/online/http.txt",
    "https://raw.githubusercontent.com/sunny9577/proxy-scraper/master/proxies.txt",
    "https://raw.githubusercontent.com/UptimerBot/proxy-list/main/proxies/http.txt",
    "https://raw.githubusercontent.com/rdavydov/proxy-list/main/proxies/http.txt",
    "https://spys.me/proxy.txt",
    "https://spys.me/socks.txt",
    "https://www.proxy-list.download/api/v1/get?type=http",
    "https://www.proxyscan.io/download?type=http",
    "https://yakumo.rei.my.id/ALL",
    "https://github.com/hookzof/socks5_list/raw/master/proxy.txt",
    "https://sunny9577.github.io/proxy-scraper/proxies.txt",
    "https://github.com/sunny9577/proxy-scraper/raw/master/proxies.txt",
    "https://raw.githubusercontent.com/B4RC0DE-TM/proxy-list/main/HTTP.txt",
    "https://raw.githubusercontent.com/RX4096/proxy-list/main/online/all.txt",
    "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies_anonymous/http.txt",
    "https://raw.githubusercontent.com/shiftytr/proxy-list/master/proxy.txt",
    "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt",
    "https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/http.txt",
    "https://raw.githubusercontent.com/BlackSnowDot/proxylist-update-every-minute/main/https.txt",
    "https://raw.githubusercontent.com/BlackSnowDot/proxylist-update-every-minute/main/http.txt",
    "https://raw.githubusercontent.com/opsxcq/proxy-list/master/list.txt",
    "https://raw.githubusercontent.com/UserR3X/proxy-list/main/online/https.txt",
]

output_file = get_relative_path("assets/proxies/" + dt.now().strftime("%Y-%m-%d"))
class_list = extract_proxies_from_file(output_file)
results = list(set(obj.proxy for obj in class_list)) if class_list else []

for url in urls:
    try:
        # response = build_request(endpoint=url, no_cache=True)
        response = get_with_proxy(url, cache_expiration=5 * 60 * 60)
        if response and response.ok:
            text = decompress_requests_response(response)
            class_list = extract_proxies(text)
            proxy_list = list(set(obj.proxy for obj in class_list))
            results.extend(proxy_list)
    except Exception:
        print(f"fail fetch proxy from {url}")

# Ensure 'results' contains only unique values
results = list(set(results))

print(f"got {len(results)} proxies")

write_file(output_file, "\n".join(results) + "\n\n")
