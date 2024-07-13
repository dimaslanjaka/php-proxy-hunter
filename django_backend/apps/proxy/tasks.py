# django_backend/apps/proxy/tasks.py

from datetime import datetime
import os
import random
import string
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
import threading
from typing import Any

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))

from src.func import (file_append_str, file_remove_empty_lines,
                      get_relative_path, remove_string_and_move_to_file, write_file)
from src.func_console import green, red
from src.func_proxy import check_proxy, is_port_open, log_proxy, upload_proxy
from src.ProxyDB import ProxyDB


def get_runner_id(identifier: Any):
    id = str(identifier)
    return get_relative_path(f'tmp/runner/{id}.lock')


def check_proxy_async(proxy_data: str):
    db = ProxyDB()
    logfile = get_relative_path('proxyChecker.txt')
    status = None
    working = False
    protocols = []
    proxies = db.extract_proxies(proxy_data)
    for proxyClass in proxies:
        if not is_port_open(proxyClass.proxy):
            log_proxy(f"{proxyClass.proxy} {red('port closed')}")
            status = "port-closed"
        else:
            # Define a function to handle check_proxy with the correct arguments
            def handle_check(protocol, url):
                return check_proxy(proxyClass.proxy, protocol, url)

            # Create a ThreadPoolExecutor
            with ThreadPoolExecutor(max_workers=3) as executor:
                # Submit the tasks
                checks = [
                    executor.submit(handle_check, 'http', "http://httpbin.org/ip"),
                    executor.submit(handle_check, 'socks4', "http://httpbin.org/ip"),
                    executor.submit(handle_check, 'socks5', "http://httpbin.org/ip")
                ]

                # Iterate through the completed tasks
                for i, future in enumerate(as_completed(checks)):
                    protocol = ['HTTP', 'SOCKS4', 'SOCKS5'][i]
                    check = future.result()

                    if check.result:
                        log = f"> {proxyClass.proxy} ✓ {protocol}"
                        protocols.append(protocol.lower())
                        file_append_str(logfile, log)
                        print(green(log))
                        working = True
                    else:
                        log = f"> {proxyClass.proxy} 🗙 {protocol}"
                        file_append_str(logfile, f"{log} -> {check.error}")
                        print(f"{red(log)} -> {check.error}")
                        working = False

                if not working:
                    status = 'dead'
                else:
                    status = 'active'
                    upload_proxy(proxyClass)

        if db is not None and status is not None:
            data = {"status": status}
            if len(protocols) > 0:
                data['type'] = "-".join(protocols).upper()
            db.update_data(proxyClass.proxy, data)

        remove_string_and_move_to_file(
            get_relative_path('proxies.txt'),
            get_relative_path('dead.txt'),
            proxyClass.proxy
        )
    file_remove_empty_lines(logfile)


def run_check_proxy_async_in_thread(proxy):
    thread = threading.Thread(target=check_proxy_async, args=(proxy,))
    thread.start()
    return thread


def debug_task():
    date_time = datetime.now()
    allowed_chars = string.ascii_letters + string.punctuation
    unique = ''.join(random.choice(allowed_chars) for x in range(100))
    log = f'Debug task executed. ({date_time} - {unique})'
    print(log)
    write_file(get_relative_path('tmp/runner/x.txt'), log)
    return {'result': 'Task completed successfully', 'message': log}
