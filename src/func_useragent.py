import os
import random
import tempfile
from typing import Union, Optional
from selenium import webdriver
from selenium.webdriver.chrome.options import Options as WebdriverOptions

from src.func import get_relative_path, read_file, write_file


def get_pc_useragent() -> Union[str, None]:
    """
    Get the current device user agent.

    Returns:
        Union[str, None]: The user agent string if available, otherwise None.
    """
    cache_file = get_relative_path('tmp/pc_useragent.txt')
    result = None
    if os.path.exists(cache_file):
        result = read_file(cache_file)
        if result and result.strip():
            return result
    driver: Optional[webdriver.Chrome] = None
    try:
        chrome_options = WebdriverOptions()
        chrome_options.add_argument("--headless=new")
        # chrome_options.add_argument("--disable-extensions")
        chrome_options.add_argument("--disable-gpu")
        chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
        driver = webdriver.Chrome(options=chrome_options)
        result = driver.execute_script("return navigator.userAgent")
        if isinstance(result, str) and result.strip():
            write_file(cache_file, result)
    except Exception:
        pass
    finally:
        if driver is not None:
            driver.quit()
    return result


def random_windows_ua() -> str:
    """
    Generates a random user agent string for Windows operating system.

    Returns:
        str: Random user agent string.
    """
    # Array of Windows versions
    windows_versions = ['Windows 7', 'Windows 8', 'Windows 10', 'Windows 11']

    # Array of Chrome versions
    chrome_versions = [
        '86.0.4240',
        '98.0.4758',
        '100.0.4896',
        '105.0.5312',
        '110.0.5461',
        '115.0.5623',
        '120.0.5768',
        '124.0.6367.78',  # Windows and Linux version
        '124.0.6367.79',  # Mac version
        '124.0.6367.82',  # Android version
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