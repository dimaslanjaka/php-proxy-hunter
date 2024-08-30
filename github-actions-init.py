import os

import chromedriver_autoinstaller
from selenium import webdriver


def display_test():
    from pyvirtualdisplay import Display

    display = Display(visible=0, size=(800, 800))
    display.start()


if os.getenv("GITHUB_ACTIONS") == "true":
    display_test()

chromedriver_autoinstaller.install()  # Check if the current version of chromedriver exists
# and if it doesn't exist, download it automatically,
# then add chromedriver to path

chrome_options = webdriver.ChromeOptions()
# Add your options as needed
options = [
    # Define window size here
    "--window-size=1200,1200",
    "--ignore-certificate-errors",
    # "--headless",
    # "--disable-gpu",
    # "--window-size=1920,1200",
    # "--ignore-certificate-errors",
    # "--disable-extensions",
    # "--no-sandbox",
    # "--disable-dev-shm-usage",
    # '--remote-debugging-port=9222'
]

for option in options:
    chrome_options.add_argument(option)


driver = webdriver.Chrome(options=chrome_options)

driver.get("https://github.com")
print(driver.title)
driver.quit()
