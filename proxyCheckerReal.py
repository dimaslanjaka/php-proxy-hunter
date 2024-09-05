import argparse
import concurrent.futures
import os
import random
import threading
import time
from multiprocessing.pool import ThreadPool as Pool
from typing import Dict, List, Optional

from bs4 import BeautifulSoup
from joblib import Parallel, delayed
from proxy_checker import ProxyChecker
from proxy_hunter import (
    build_request,
    check_raw_headers_keywords,
    decompress_requests_response,
    delete_path,
    extract_proxies,
    file_append_str,
    read_all_text_files,
    read_file,
    sanitize_filename,
    truncate_file_content,
)

from src.func_date import is_date_rfc3339_hour_more_than
from proxyWorking import ProxyWorkingManager
from src.func import get_relative_path
from src.func_console import green, log_proxy, red
from src.func_proxy import check_proxy
from src.ProxyDB import ProxyDB


class ProxyCheckerReal:
    def __init__(self, log_mode: str = "text"):
        self.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Accept-Language": "en-US,en;q=0.9",
        }
        self.log_mode = log_mode

    def log(self, *args, **kwargs):
        if self.log_mode == "html":
            log_proxy(ansi_html=True, *args, **kwargs)
        else:
            log_proxy(*args, **kwargs)

    def real_check(
        self,
        proxy: str,
        url: str,
        title_should_be: str,
        cancel_event: Optional[threading.Event] = None,
    ):
        """Check proxy with matching the title of response."""
        protocols = []
        output_file = get_relative_path(f"tmp/logs/{sanitize_filename(proxy)}.txt")
        if os.path.exists(output_file):
            truncate_file_content(output_file)
        response_title = ""

        def check_proxy_with_cancellation(proxy_type: str):
            if cancel_event and cancel_event.is_set():
                return None  # Early exit if cancellation is requested
            return check_proxy(
                proxy, proxy_type, url, self.headers, cancel_event=cancel_event
            )

        executor = concurrent.futures.ThreadPoolExecutor(max_workers=3)
        future_to_proxy_type = {
            executor.submit(check_proxy_with_cancellation, proxy_type): proxy_type
            for proxy_type in ["socks4", "http", "socks5"]
        }

        for future in concurrent.futures.as_completed(future_to_proxy_type):
            proxy_type = future_to_proxy_type[future]
            if cancel_event and cancel_event.is_set():
                self.log(
                    f"Cancellation requested, stopping check for {proxy_type}://{proxy}."
                )
                break
            try:
                check = future.result()
                if check is None:
                    continue  # Skip if the result is None due to cancellation
                log = f"{check.type}://{check.proxy}\n"
                log += f"RESULT: {'true' if check.result else 'false'}\n"
                if not check.result and check.error:
                    log += f"ERROR: {check.error.strip()}\n"
                if check.response and check.response.ok:
                    log += "RESPONSE HEADERS:\n"
                    for key, value in check.response.headers.items():
                        log += f"  {key}: {value}\n"
                    response_text = decompress_requests_response(check.response)
                    if response_text:
                        soup = BeautifulSoup(response_text, "html.parser")
                        response_title = soup.title.string.strip() if soup.title else ""
                        if (
                            not check_raw_headers_keywords(response_text)
                            and response_title.lower() != "AZ Environment".lower()
                        ):
                            log += f"TITLE: {response_title}\n"
                            if title_should_be.lower() in response_title.lower():
                                protocols.append(check.type.lower())
                file_append_str(output_file, log)
            except Exception as exc:
                self.log(f"{proxy_type} check generated an exception: {exc}")

        # if os.path.exists(output_file):
        #     self.log(f"Logs written {output_file}")

        result = {
            "result": False,
            "url": url,
            "https": url.startswith("https://"),
            "proxy": proxy,
            "type": protocols,
        }
        if protocols and response_title:
            pt = "-".join(protocols)
            self.log(
                f"{pt}://{proxy} {green('working')} -> {url} ({response_title})".replace(
                    "()", ""
                ).strip()
            )
            result["result"] = True
        else:
            self.log(
                f"{proxy} {red('dead')} -> {url} ({response_title})".replace(
                    "()", ""
                ).strip()
            )
            result["result"] = False
        return result

    def real_anonymity(self, proxy: str):
        """Get proxy anonymity."""
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
                    self.log(f"{proxy} anonymity is {green(result)}")
                    # break when success
                    break
        return result

    def real_latency(self, proxy: str):
        """Get proxy latency."""
        latency = None
        latency_log = None
        valid_log = None
        configs = [
            ["https://bing.com", "bing"],
            ["https://github.com/", "github"],
            ["https://www.example.com/", "example"],
            ["http://httpforever.com/", "http forever"],
            ["http://www.example.net/", "example"],
            ["http://www.example.com/", "example"],
        ]
        for config in configs:
            url, title_should_be = tuple(config)
            response = None
            start_time = 0
            end_time = 0

            try:
                start_time = time.time()
                response = build_request(
                    proxy, "http", endpoint=url, allow_redirects=True
                )
                end_time = time.time()
            except Exception:
                pass

            if response and not response.ok:
                try:
                    start_time = time.time()
                    response = build_request(
                        proxy, "socks4", endpoint=url, allow_redirects=True
                    )
                    end_time = time.time()
                except Exception:
                    pass

            if response and not response.ok:
                try:
                    start_time = time.time()
                    response = build_request(
                        proxy, "socks5", endpoint=url, allow_redirects=True
                    )
                    end_time = time.time()
                except Exception:
                    pass

            if response and response.ok:
                # Get latency milliseconds
                latency = int(end_time - start_time) * 1000
                response_text = decompress_requests_response(response)
                soup = BeautifulSoup(response_text, "html.parser")
                response_title = (
                    soup.title.string.strip()
                    if soup.title and soup.title.string
                    else "Empty Title"
                )
                valid_log = (
                    green("accurate")
                    if title_should_be.lower() in response_title.lower()
                    else red("inaccurate")
                    + f' page title should be "{title_should_be.lower()}" but "{response_title.lower()}"'
                )
                latency_log = (
                    green(f"{latency} ms") if latency > 0 else red(str(latency))
                )

                if title_should_be.lower() in response_title.lower():
                    break
        if valid_log and latency_log:
            self.log(f"{proxy} latency is {latency_log} [{valid_log}]")
        return latency


instance_checker = ProxyCheckerReal()


def real_check(
    proxy: str,
    url: str,
    title_should_be: str,
    cancel_event: Optional[threading.Event] = None,
):
    """check proxy with matching the title of response"""
    global instance_checker
    return instance_checker.real_check(proxy, url, title_should_be, cancel_event)


def real_anonymity(proxy: str):
    """
    get proxy anonymity
    """
    global instance_checker
    return instance_checker.real_anonymity(proxy)


def real_latency(proxy: str):
    """
    get proxy latency
    """
    global instance_checker
    return instance_checker.real_latency(proxy)


def worker(item: Dict[str, str]):
    try:
        db = ProxyDB(get_relative_path("src/database.sqlite"))
        test = {}
        if not test.get("result"):
            test = real_check(
                item["proxy"], "https://www.axis.co.id/bantuan", "pusat layanan"
            )
        if not test.get("result"):
            test = real_check(
                item["proxy"], "https://www.ssl.org/", "SSL Certificate Checker"
            )
        if not test.get("result"):
            test = real_check(item["proxy"], "http://httpforever.com/", "HTTP Forever")

        if test.get("result"):
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
    limit = 100
    if args.max:
        limit = args.max

    db = ProxyDB(get_relative_path("src/database.sqlite"), True)

    # proxy = "18.169.133.105:132"
    # sc = real_check(proxy, "http://httpforever.com/", "http forever")
    # print(sc)

    files_content = read_all_text_files(get_relative_path("assets/proxies"))
    if os.path.exists(get_relative_path("proxies.txt")):
        files_content[get_relative_path("proxies.txt")] = read_file(
            get_relative_path("proxies.txt")
        )
    for file_path, content in files_content.items():
        extract = extract_proxies(content)
        print(f"Total proxies extracted from {file_path} is {len(extract)}")
        db.extract_proxies(content, True)
        delete_path(file_path)

    proxies = db.get_working_proxies(False)
    proxies = [
        item
        for item in proxies
        if is_date_rfc3339_hour_more_than(item.get("last_check"), 4)
        # filter only working proxies checked more than 4 hours
    ]
    if not proxies or len(proxies) < 100:
        proxies = db.get_untested_proxies(limit)
    if not proxies:
        proxies = db.get_all_proxies(True)[:limit]
    random.shuffle(proxies)

    # using_pool(proxies, 5)
    using_joblib(proxies[:limit], 5)
    # test()

    db.close()
