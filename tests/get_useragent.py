from pprint import pprint
import sys
import os

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_browser import get_pc_useragent

# from selenium import webdriver
# from selenium.webdriver.chrome.options import Options
# def get_pc_useragent():
#     chrome_options = Options()
#     chrome_options.add_argument("--headless=new")
#     chrome_options.add_argument("--disable-extensions")
#     chrome_options.add_argument("--disable-gpu")
#     driver = webdriver.Chrome(options=chrome_options)
#     useragent = driver.execute_script("return navigator.userAgent")
#     driver.quit()
#     return useragent


if __name__ == "__main__":
    user_agent = get_pc_useragent()
    if user_agent:
        print("Your PC's User-Agent:", user_agent)
    else:
        print("failed get User-Agent")
