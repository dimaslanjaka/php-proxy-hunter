import os
import random
import sys
from typing import Union

import requests
from proxy_hunter.utils import read_file

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


def get_pc_useragent() -> Union[str, None]:
    """
    Get the current device user agent.

    Returns:
        Union[str, None]: The user agent string if available, otherwise None.
    """
    cache_file = "tmp/data/pc_useragent.txt"
    os.makedirs(os.path.dirname(cache_file), 777, True)
    # result = None
    if os.path.exists(cache_file):
        result = read_file(cache_file)
        if result and result.strip():
            return result
    # driver: Optional[webdriver.Chrome] = None
    # try:
    #     chrome_options = WebdriverOptions()
    #     chrome_options.add_argument("--headless=new")
    #     # chrome_options.add_argument("--disable-extensions")
    #     chrome_options.add_argument("--disable-gpu")
    #     chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
    #     driver = webdriver.Chrome(options=chrome_options)
    #     result = driver.execute_script("return navigator.userAgent")
    #     if isinstance(result, str) and result.strip():
    #         write_file(cache_file, result)
    # except Exception:
    #     pass
    # finally:
    #     if driver is not None:
    #         driver.quit()
    # return result
    headers = requests.get("https://www.example.com").headers
    user_agent = headers.get("User-Agent")
    default_user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
    return user_agent or default_user_agent


def random_windows_ua() -> str:
    """
    Generates a random user agent string for Windows operating system.

    Returns:
        str: Random user agent string.
    """
    # Array of Windows versions
    windows_versions = ["Windows 7", "Windows 8", "Windows 10", "Windows 11"]

    # Array of Chrome versions
    chrome_versions = [
        "86.0.4240",
        "98.0.4758",
        "100.0.4896",
        "105.0.5312",
        "110.0.5461",
        "115.0.5623",
        "120.0.5768",
        "124.0.6367.78",  # Windows and Linux version
        "124.0.6367.79",  # Mac version
        "124.0.6367.82",  # Android version
    ]

    # Randomly select a Windows version
    random_windows = random.choice(windows_versions)

    # Randomly select a Chrome version
    random_chrome = random.choice(chrome_versions)

    # Generate random Safari version and AppleWebKit version
    random_safari_version = f"{random.randint(600, 700)}.{random.randint(0, 99)}"
    random_applewebkit_version = f"{random.randint(500, 600)}.{random.randint(0, 99)}"

    # Construct and return the user agent string
    return f"Mozilla/5.0 ({random_windows}) AppleWebKit/{random_applewebkit_version} (KHTML, like Gecko) Chrome/{random_chrome} Safari/{random_safari_version}"
