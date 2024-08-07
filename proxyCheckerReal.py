import argparse
import os
import random
from multiprocessing.pool import ThreadPool as Pool
import time
from typing import Dict, List

from bs4 import BeautifulSoup
from joblib import Parallel, delayed

from src.func import (
    file_append_str,
    get_relative_path,
    sanitize_filename,
    truncate_file_content,
)
from src.func_console import green, red, log_proxy
from src.func_proxy import check_proxy, build_request
from src.ProxyDB import ProxyDB
from proxy_checker import ProxyChecker
from proxyWorking import ProxyWorkingManager


def real_check(proxy: str, url: str, title_should_be: str):
    """check proxy with matching the title of response"""
    # log_proxy('=' * 30)
    # log_proxy(f"CHECKING {proxy}")
    # log_proxy('=' * 30)

    protocols = []
    output_file = get_relative_path(f"tmp/logs/{sanitize_filename(proxy)}.txt")
    if os.path.exists(output_file):
        truncate_file_content(output_file)
    response_title = ""

    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
        "Accept-Language": "en-US,en;q=0.9",
    }

    checks = {
        "socks4": check_proxy(proxy, "socks4", url, headers),
        "http": check_proxy(proxy, "http", url, headers),
        "socks5": check_proxy(proxy, "socks5", url, headers),
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
                soup = BeautifulSoup(check.response.text, "html.parser")
                response_title = soup.title.string.strip() if soup.title else ""
                log += f"TITLE: {response_title}\n"
                if title_should_be.lower() in response_title.lower():
                    protocols.append(proxy_type.lower())
            file_append_str(output_file, log)

    if os.path.exists(output_file):
        log_proxy(f"logs written {output_file}")

    result = {
        "result": False,
        "url": url,
        "https": url.startswith("https://"),
        "proxy": proxy,
        "type": protocols,
    }
    if protocols:
        log_proxy(
            f"{proxy} {green('working')} -> {url} ({response_title})".replace(
                "()", ""
            ).strip()
        )
        result["result"] = True
    else:
        log_proxy(
            f"{proxy} {red('dead')} -> {url} ({response_title})".replace(
                "()", ""
            ).strip()
        )
        result["result"] = False
    return result


def real_anonymity(proxy: str):
    """
    get proxy anonymity
    """
    checker = ProxyChecker(60000, False)
    result = None
    for url in checker.proxy_judges:
        response = None
        try:
            response = build_request(proxy, "http", endpoint=url)
        except Exception:
            pass
        if response and not response.ok:
            try:
                response = build_request(proxy, "socks4", endpoint=url)
            except Exception:
                pass
        if response and not response.ok:
            try:
                response = build_request(proxy, "socks5", endpoint=url)
            except Exception:
                pass
        if response and response.ok:
            soup = BeautifulSoup(response.text, "html.parser")
            response_title = soup.title.string.strip() if soup.title else ""
            if "AZ Environment".lower() in response_title.lower():
                result = checker.parse_anonymity(response.text)
                log_proxy(f"{proxy} anonymity is {green(result)}")
                # break when success
                break
    return result


def real_latency(proxy: str):
    """
    get proxy latency
    """
    latency = None
    configs = [
        ["https://bing.com", "bing"],
        ["https://github.com/", "github"],
        ["http://httpforever.com/", "http forever"],
        ["http://www.example.net/", "example domain"],
    ]
    for config in configs:
        url, title_should_be = tuple(config)
        response = None
        start_time = 0
        end_time = 0

        try:
            start_time = time.time()
            response = build_request(proxy, "http", endpoint=url)
            end_time = time.time()
        except Exception:
            pass

        if response and not response.ok:
            try:
                start_time = time.time()
                response = build_request(proxy, "socks4", endpoint=url)
                end_time = time.time()
            except Exception:
                pass

        if response and not response.ok:
            try:
                start_time = time.time()
                response = build_request(proxy, "socks5", endpoint=url)
                end_time = time.time()
            except Exception:
                pass

        if response and response.ok:
            latency = int(end_time - start_time)
            soup = BeautifulSoup(response.text, "html.parser")
            response_title = soup.title.string.strip() if soup.title else ""
            if title_should_be.lower() in response_title.lower():
                log_proxy(f"{proxy} latency is {green(latency)} seconds")
                # break when success
                break
    return latency


def worker(item: Dict[str, str]):
    try:
        db = ProxyDB(get_relative_path("src/database.sqlite"))
        test = real_check(
            item["proxy"], "https://www.axis.co.id/bantuan", "pusat layanan"
        )

        if not test["result"]:
            test = real_check(item["proxy"], "https://www.example.com/", "example")

        if not test["result"]:
            test = real_check(item["proxy"], "http://azenv.net/", "AZ Environment")

        if not test["result"]:
            test = real_check(item["proxy"], "http://httpforever.com/", "HTTP Forever")

        if test["result"]:
            db.update_data(
                item["proxy"],
                {
                    "status": "active",
                    "https": "true" if test["https"] else "false",
                    "type": ("-".join(test["type"]).lower() if "type" in test else ""),
                },
            )
            # write working.json
            wmg = ProxyWorkingManager()
            wmg._load_db()
        else:
            db.update_status(item["proxy"], "dead")
        return test

    except Exception as e:
        log_proxy(f"Error processing item {item}: {e}")
        return {"result": False, "error": e}

    finally:
        db.close()
        return {"result": False}


def using_pool(proxies: List[Dict[str, str]], pool_size: int = 5):
    """
    multi threading using pool
    """
    pool = Pool(pool_size)
    for item in proxies[:100]:
        if not item:
            continue
        pool.apply_async(worker, (item,))
    pool.close()
    pool.join()
    return pool


def using_joblib(proxies: List[Dict[str, str]], pool_size: int = 5):
    return Parallel(n_jobs=pool_size)(delayed(worker)(item) for item in proxies)


def test():
    proxy = "45.138.87.238:1080"  # 35.185.196.38:3128
    cek = real_check(proxy, "https://bing.com", "bing")
    if not cek["result"]:
        log_proxy(f"{proxy} dead")
    if not real_anonymity(proxy):
        log_proxy(f"{proxy} fail get anonymity")
    if not real_latency(proxy):
        log_proxy(f"{proxy} fail get latency")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Proxy Tool")
    parser.add_argument("--max", type=int, help="Maximum number of proxies to check")
    args = parser.parse_args()
    max = 100
    if args.max:
        max = args.max

    db = ProxyDB(get_relative_path("src/database.sqlite"))
    proxies: List[Dict[str, str | None]] = db.get_all_proxies(True)[:max]
    random.shuffle(proxies)

    # using_pool(proxies, 5)
    using_joblib(proxies, 5)
    # test()

    # close database
    db.close()
