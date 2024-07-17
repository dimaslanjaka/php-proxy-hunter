import requests
from bs4 import BeautifulSoup
from src.ProxyDB import ProxyDB
from src.func import *
from src.func_console import *


def real_check(proxy):
    print('=' * 27)
    print(f"CHECKING {proxy}")
    print('=' * 27)

    protocols = []

    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
        'Accept-Language': 'en-US,en;q=0.9',
    }

    def check_proxy(proxy, proxy_type, url, headers):
        try:
            response = requests.get(url, proxies={proxy_type: proxy}, headers=headers, timeout=10)
            response.raise_for_status()
            return {
                'type': proxy_type,
                'proxy': proxy,
                'result': True,
                'response-headers': dict(response.headers.items()),
                'body': response.text,
            }
        except requests.RequestException as e:
            return {
                'type': proxy_type,
                'proxy': proxy,
                'result': False,
                'error': str(e),
                'response-headers': {},
                'body': '',
            }

    url = 'http://azenv.net/'
    title_should_be = 'AZ Environment'

    checks = {
        'socks4': check_proxy(proxy, 'socks4', url, headers),
        'http': check_proxy(proxy, 'http', url, headers),
        'socks5': check_proxy(proxy, 'socks5', url, headers),
    }

    for proxy_type, check in checks.items():
        log = f"{check['type']}://{check['proxy']}\n"
        log += f"RESULT: {'true' if check['result'] else 'false'}\n"
        if not check['result']:
            log += f"ERROR: {check['error'].strip()}\n"
        log += "RESPONSE HEADERS:\n"
        for key, value in check['response-headers'].items():
            log += f"  {key}: {value}\n"
        if check['body']:
            soup = BeautifulSoup(check['body'], 'html.parser')
            title = soup.title.string.strip() if soup.title else ''
            log += f"TITLE: {title}\n"
            if title_should_be.lower() in title.lower():
                protocols.append(check['type'].lower())
        print(log)

    if protocols:
        print(f"{proxy} {green('working')}")
        return True
    else:
        print(f"{proxy} {red('dead')}")
        return False


if __name__ == "__main__":
    db = ProxyDB(get_relative_path('src/database.sqlite'))
    proxies = db.get_all_proxies()
    random.shuffle(proxies)
    for item in proxies[:100]:
        if not item:
            continue
        if real_check(item['proxy']):
            db.update_status(item['proxy'], 'active')
        else:
            db.update_status(item['proxy'], 'dead')
