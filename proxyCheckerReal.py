from bs4 import BeautifulSoup
from src.ProxyDB import ProxyDB
from src.func import get_relative_path, file_append_str, sanitize_filename, truncate_file_content
from src.func_console import red, green
from src.func_proxy import check_proxy
import random


def real_check(proxy: str, url: str, title_should_be: str):
    print('=' * 30)
    print(f"CHECKING {proxy}")
    print('=' * 30)

    protocols = []
    output_file = get_relative_path(f"tmp/logs/{sanitize_filename(proxy)}.txt")
    truncate_file_content(output_file)

    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
        'Accept-Language': 'en-US,en;q=0.9',
    }

    checks = {
        'socks4': check_proxy(proxy, 'socks4', url, headers),
        'http': check_proxy(proxy, 'http', url, headers),
        'socks5': check_proxy(proxy, 'socks5', url, headers),
    }

    for proxy_type, check in checks.items():
        log = f"{check.type}://{check.proxy}\n"
        log += f"RESULT: {'true' if check.result else 'false'}\n"
        if not check.result:
            log += f"ERROR: {check.error.strip()}\n"
        if check.response:
            log += "RESPONSE HEADERS:\n"
            for key, value in check.response.headers.items():
                log += f"  {key}: {value}\n"
            if check.response.text:
                soup = BeautifulSoup(check.response.text, 'html.parser')
                title = soup.title.string.strip() if soup.title else ''
                log += f"TITLE: {title}\n"
                if title_should_be.lower() in title.lower():
                    protocols.append(proxy_type.lower())
            file_append_str(output_file, log)
    print(f"result writen {output_file}")

    result = {
        'result': False,
        'url': url,
        'https': url.startswith('https://'),
        'proxy': proxy,
        'protocols': protocols
    }
    if protocols:
        print(f"{proxy} {green('working')}")
        result['result'] = True
    else:
        print(f"{proxy} {red('dead')}")
        result['result'] = False
    return result


if __name__ == "__main__":
    db = ProxyDB(get_relative_path('src/database.sqlite'))
    proxies = db.get_all_proxies()
    random.shuffle(proxies)
    for item in proxies[:100]:
        if not item:
            continue
        test = real_check(item['proxy'], 'https://www.axis.co.id/bantuan', 'pusat layanan')
        if not test['result']:
            test = real_check(item['proxy'], 'http://azenv.net/', 'AZ Environment')
        if test['result']:
            db.update_data(item['proxy'], {
                'status': 'active',
                'https': str(test['https']).lower()
            })
        else:
            db.update_status(item['proxy'], 'dead')
