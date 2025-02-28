import os
import sys

sys.path.append(
    os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../../"))
)

import subprocess
import time

from django.conf import settings
from huey import crontab
from huey.contrib.djhuey import db_task, on_commit_task, periodic_task, task

from django_backend.apps.proxy.tasks_unit.geolocation import fetch_geo_ip
from django_backend.apps.proxy.utils import execute_select_query
from src.func import get_relative_path
from src.func_console import log_file

# on development run
# python manage.py run_huey


@periodic_task(crontab(minute="*/10"))
def run_geolocation():
    """
    Run proxy geolocation for missing proxy information
    """
    proxies = execute_select_query(
        "SELECT * FROM proxies WHERE status = ? AND (timezone IS NULL OR country IS NULL OR lang IS NULL)",
        ("active",),
    )
    for item in proxies:
        proxy = item.get("proxy", "")
        if isinstance(proxy, str) and proxy:
            fetch_geo_ip(proxy)


@periodic_task(crontab(minute="*/10"))
def run_check_proxies():
    tprint("this task run every 10 minutes")
    try:
        # Specify the command and directory
        command = "python manage.py check_proxies --max=1"
        # Change to the base directory of your Django project
        base_directory = settings.BASE_DIR

        # Run the shell command
        result = subprocess.run(
            command,
            shell=True,
            cwd=base_directory,
            check=True,
            capture_output=True,
            text=True,
        )

        # Print or log the output of the command
        print(f"Command output: {result.stdout}")
        if result.stderr:
            print(f"Command error output: {result.stderr}")

    except subprocess.CalledProcessError as e:
        print(f"Error occurred: {e}")


def tprint(s, c=32):
    # Helper to print messages from within tasks using color, to make them
    # stand out in examples.
    print("\x1b[1;%sm%s\x1b[0m" % (c, s))


# Tasks used in examples.


# @task()
# def huey_add(a, b):
#     return a + b


# @task()
# def mul(a, b):
#     return a * b


# @db_task()  # Opens DB connection for duration of task.
# def slow(n):
#     tprint("going to sleep for %s seconds" % n)
#     time.sleep(n)
#     tprint("finished sleeping for %s seconds" % n)
#     return n


# @task(retries=1, retry_delay=5, context=True)
# def flaky_task(task=None):
#     if task is not None and task.retries == 0:
#         tprint("flaky task succeeded on retry.")
#         return "succeeded on retry."
#     tprint("flaky task is about to raise an exception.", 31)
#     raise Exception("flaky task failed!")


# Periodic tasks.

# n = 1


# @periodic_task(crontab(minute=f"*/{n}"))
# def every_n_minute():
#     global n
#     tprint(f"This task runs every {n} minutes.", 35)
#     log_file(
#         get_relative_path("proxyChecker.txt"),
#         f"this task huey run periodically {n} minutes",
#     )


# When this task is called, it will not be enqueued until the active
# transaction commits. If no transaction is active it will enqueue immediately.
# Example:
# with transaction.atomic():
#     rh = after_commit('hello!')
#     time.sleep(5)  # Still not enqueued....
#
# # Now the task is enqueued.
# print(rh.get(True))  # prints "6".
# @on_commit_task()
# def after_commit(msg):
#     tprint(msg, 33)
#     return len(msg)
