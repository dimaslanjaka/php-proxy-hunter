# django_backend/apps/proxy/tasks.py

import os
import sys

from celery import shared_task

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../')))
from concurrent.futures import ThreadPoolExecutor, as_completed

from celery import shared_task

from src.func import (file_append_str, file_remove_empty_lines,
                      get_relative_path, remove_string_and_move_to_file)
from src.func_console import green, red
from src.func_proxy import (check_proxy, check_proxy_new, is_port_open, log_proxy,
                            upload_proxy)
from src.ProxyDB import ProxyDB


@shared_task
def check_proxy_async(proxy):
    db = ProxyDB()
    logfile = get_relative_path('proxyChecker.txt')
    status = None
    working = False
    protocols = []
    print(f"check_proxy_async -> {proxy}")

    if not is_port_open(proxy):
        log_proxy(f"{proxy} {red('port closed')}")
        status = "port-closed"
    else:
        # Define a function to handle check_proxy with the correct arguments
        def handle_check(protocol, url):
            return check_proxy(proxy, protocol, url)

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
                    log = f"> {proxy} ✓ {protocol}"
                    protocols.append(protocol.lower())
                    file_append_str(logfile, log)
                    print(green(log))
                    working = True
                else:
                    log = f"> {proxy} 🗙 {protocol}"
                    file_append_str(logfile, f"{log} -> {check.error}")
                    print(f"{red(log)} -> {check.error}")
                    working = False

            if not working:
                status = 'dead'
            else:
                status = 'active'
                upload_proxy(proxy)

    if db is not None and status is not None:
        data = {"status": status}
        if len(protocols) > 0:
            data['type'] = "-".join(protocols).upper()
        db.update_data(proxy, data)

    remove_string_and_move_to_file(
        get_relative_path('proxies.txt'),
        get_relative_path('dead.txt'),
        proxy
    )
    file_remove_empty_lines(logfile)


@shared_task
def doCrawl(task_id):
    # Your crawling logic here
    print(f"Starting crawl for task: {task_id}")
    # Simulate a long-running task
    import time
    time.sleep(10)
    print(f"Finished crawl for task: {task_id}")


@shared_task
def debug_task():
    print('Debug task executed.')
    return 'Task completed successfully'


@shared_task
def check_proxy_task(proxy):
    # Call your existing function with the proxy argument
    return check_proxy_new(proxy)
