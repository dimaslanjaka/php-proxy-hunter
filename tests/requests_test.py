# nuitka tests/requests_test.py --output-dir=dist --output-file=DL-Traffic.exe --onefile --enable-console --include-data-file=proxies.txt=proxies.txt

import time
import requests


def main():
    try:
        session = requests.Session()
        # proxy = "http://139.178.66.228:9443"
        # session.proxies = {
        #     "http": proxy,
        #     "https": proxy
        # }
        response = session.post(
            url="https://sh.webmanajemen.com/proxyAdd.php",
            data={"proxies": "proxies_data"},
            cookies={"__ga": "value", "_ga": "value"},
        )
        print(response.status_code, response.text)
        time.sleep(1000)
    except Exception as e:
        print("An error occurred:", e)


if __name__ == "__main__":
    main()
