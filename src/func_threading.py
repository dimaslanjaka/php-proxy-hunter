import subprocess
import threading
from typing import Callable, List, Any
import concurrent.futures
import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


def kill_processes(process_names: List[str]) -> None:
    """
    Kills processes with given names using WMIC.

    Args:
        process_names (List[str]): List of process names to kill.

    Returns:
        None
    """
    for name in process_names:
        subprocess.run(
            ["wmic", "process", "where", f'name like "{name}"', "delete"], check=True
        )


def kills():
    process_names = [
        "chrome.exe",
        "webdriver.exe",
        "chromedriver.exe",
        "php.exe",
        "python.exe",
        "node.exe",
        "dl-runner.exe",
        "dl-traffic.exe",
        sys.argv[0],
    ]
    kill_processes(process_names)


def process_in_parallel(
    items: List, process_function: Callable, max_threads: int = 5
) -> None:
    """
    Process items in parallel using ThreadPoolExecutor.

    Args:
        items (List): List of items to process.
        process_function (Callable): Function to process each item.
        max_threads (int, optional): Maximum number of threads to use (default is 5).
    Example:
        ```
        def process_proxy(proxy: dict) -> None:
            print(proxy)
        proxies = get_proxies()
        process_in_parallel(proxies, process_proxy)
        ```
    """
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_threads) as executor:
        # Submit each item processing task to the executor
        futures = [executor.submit(process_function, item) for item in items]

        # Wait for all tasks to complete
        concurrent.futures.wait(futures)


def background_function_decorator(func):
    """
    A decorator function to run the given function in a background thread.

    Args:
        func (callable): The function to run in the background.

    Returns:
        callable: A wrapper function that runs the given function in a background thread.

    Example:
        ```
        # Example function to be decorated
        def the_function(url, data={}, headers=[]):
            print("Performing some task with URL:", url)
            print("Data:", data)
            print("Headers:", headers)
            # Perform some task with the provided arguments

        run_func_in_background = background_function_decorator(the_function)
        run_func_in_background(url, data={}, headers=[])
        ```
    """

    def wrapper(*args: Any, **kwargs: Any) -> None:
        """
        Wrapper function to execute the provided function in a background thread.

        Args:
            *args: Positional arguments to pass to the function.
            **kwargs: Keyword arguments to pass to the function.
        """
        thread = threading.Thread(target=func, args=args, kwargs=kwargs)
        thread.daemon = True
        thread.start()

    return wrapper
