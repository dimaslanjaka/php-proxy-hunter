import os
import stat
import chromedriver_autoinstaller
from selenium import webdriver


def display_test():
    from pyvirtualdisplay import Display

    try:
        display = Display(visible=0, size=(800, 800))
        display.start()
    except Exception as e:
        print(f"pyvirtualdisplay error {e}")


if os.getenv("GITHUB_ACTIONS") == "true":
    display_test()


def make_dirs_and_set_permissions():
    # List of directories to create
    dirs = [".cache", "tmp", "config", "assets/proxies", "tmp/runners", "tmp/cookies"]

    # Create directories
    for dir_path in dirs:
        current_directory = os.path.dirname(os.path.abspath(__file__))
        dir_path = os.path.join(current_directory, dir_path)
        os.makedirs(dir_path, exist_ok=True)
        # Set permissions to 777
        os.chmod(dir_path, stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)


make_dirs_and_set_permissions()

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
