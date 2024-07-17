import os
import sys

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../../../'))
SRC_DIR = os.path.join(BASE_DIR, 'src')
sys.path.append(SRC_DIR)

import random
from multiprocessing.pool import ThreadPool as Pool
from typing import Dict, List

from bs4 import BeautifulSoup
from django.conf import settings
from django.core.management.base import BaseCommand
from joblib import Parallel, delayed

from src.func import (file_append_str, get_relative_path, sanitize_filename,
                      truncate_file_content)
from src.func_console import green, red
from src.func_proxy import check_proxy
from src.ProxyDB import ProxyDB
from django.db import connection
from django_backend.apps.proxy.models import Proxy


def sql_exec(sql: str):
    # Get a cursor object using the connection
    with connection.cursor() as cursor:
        # Write your raw SQL query
        sql_query = sql

        # Execute the raw SQL query
        cursor.execute(sql_query)


def real_check(proxy: str, url: str, title_should_be: str):
    protocols = []
    output_file = get_relative_path(f"tmp/logs/{sanitize_filename(proxy)}.txt")
    truncate_file_content(output_file)
    response_title = ''

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
        if not check.result and check.error:
            log += f"ERROR: {check.error.strip()}\n"
        if check.response:
            log += "RESPONSE HEADERS:\n"
            for key, value in check.response.headers.items():
                log += f"  {key}: {value}\n"
            if check.response.text:
                soup = BeautifulSoup(check.response.text, 'html.parser')
                response_title = soup.title.string.strip() if soup.title else ''
                log += f"TITLE: {response_title}\n"
                if title_should_be.lower() in response_title.lower():
                    protocols.append(proxy_type.lower())
            file_append_str(output_file, log)

    if os.path.exists(output_file):
        print(f"logs written {output_file}")

    result = {
        'result': False,
        'url': url,
        'https': url.startswith('https://'),
        'proxy': proxy,
        'protocols': protocols
    }
    if protocols:
        print(f"{proxy} {green('working')} -> {url} ({response_title})")
        result['result'] = True
    else:
        print(f"{proxy} {red('dead')} -> {url} ({response_title})")
        result['result'] = False
    return result


def worker(item: Dict[str, str]):
    db = None
    try:
        db = ProxyDB(get_relative_path('src/database.sqlite'))
    except Exception:
        pass
    try:
        test = real_check(item['proxy'], 'https://www.axis.co.id/bantuan', 'pusat layanan')

        if not test['result']:
            test = real_check(item['proxy'], 'https://www.example.com/', 'example')

        if not test['result']:
            test = real_check(item['proxy'], 'http://azenv.net/', 'AZ Environment')

        if not test['result']:
            test = real_check(item['proxy'], 'http://httpforever.com/', 'HTTP Forever')

        if test['result']:
            if db:
                db.update_data(item['proxy'], {
                    'status': 'active',
                    'https': 'true' if test['https'] else 'false',
                    'type': '-'.join(test['protocols']).lower()
                })
            https = 'true' if test['https'] else 'false'
            protocols = '-'.join(test['protocols']).lower()
            sql_exec(f"UPDATE proxies SET status = 'active', type = '{protocols}', https = '{https}' WHERE proxy = '{item['proxy']}';")
        else:
            if db:
                db.update_status(item['proxy'], 'dead')
            sql_exec(f"UPDATE proxies SET status = 'dead' WHERE proxy = '{item['proxy']}';")

    except Exception as e:
        print(f'Error processing item {item}: {str(e)}')

    finally:
        if db:
            db.close()


def using_pool(proxies: List[Dict[str, str]], pool_size: int = 5):
    """
    Multi-threading using pool
    """
    pool = Pool(pool_size)
    for item in proxies[:100]:
        if not item:
            continue
        pool.apply_async(worker, (item,))
    pool.close()
    pool.join()


def using_joblib(proxies: List[Dict[str, str]], pool_size: int = 5):
    Parallel(n_jobs=pool_size)(delayed(worker)(item) for item in proxies)


class Command(BaseCommand):
    help = 'Check the status of proxies and update the database.'

    def handle(self, *args, **kwargs):
        proxies = list(Proxy.objects.all().values())
        random.shuffle(proxies)
        using_pool(proxies, 5)
        try:
            self.handle1(*args, **kwargs)
        except Exception:
            pass

    def handle1(self, *args, **kwargs):
        db = ProxyDB(get_relative_path('src/database.sqlite'))
        proxies: List[Dict[str, str | None]] = db.get_all_proxies()
        random.shuffle(proxies)

        # using_pool(proxies, 5)
        using_joblib(proxies, 5)

        db.close()
