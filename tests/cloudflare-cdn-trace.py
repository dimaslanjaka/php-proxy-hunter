import random
import re
import traceback
import tracemalloc

tracemalloc.start()


import os
import sys

from proxy_hunter import decompress_requests_response
from requests import HTTPError, RequestException, Timeout

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from proxy_hunter import build_request

from src.func import get_relative_path
from src.geoPlugin import get_country_name
from src.ProxyDB import ProxyDB

if __name__ == "__main__":
    db = ProxyDB(get_relative_path("src/database.sqlite"), True)
    proxies = db.get_working_proxies()
    random.shuffle(proxies)
    will_break = False
    for item in proxies:
        if will_break:
            break
        protocols = item["type"].lower().split("-")
        for pt in protocols:
            if not pt:
                continue
            try:
                response = build_request(
                    item["proxy"],
                    pt,
                    "GET",
                    None,
                    endpoint="https://cloudflare.com/cdn-cgi/trace",
                )
                if response.ok:
                    text = decompress_requests_response(response)
                    print(text)

                    # Split the text into lines using regex for different line endings
                    lines = re.split(r"\r?\n", text.strip())

                    # Create dictionary from lines
                    data_dict = dict(
                        line.split("=", 1) for line in lines if "=" in line
                    )

                    # Optional: Strip whitespace from keys and values
                    data_dict = {k.strip(): v.strip() for k, v in data_dict.items()}

                    country_code = data_dict["loc"]
                    print(get_country_name(country_code))

                    will_break = True
                    break
                else:
                    print(f"{pt}://{item['proxy']} no response")
            except HTTPError as http_err:
                print(f"HTTP error occurred: {http_err}")
            except Timeout as timeout_err:
                print(f"Timeout error occurred: {timeout_err}")
            except ConnectionError as conn_err:
                print(f"Connection error occurred: {conn_err}")
            except RequestException as req_err:
                print(f"An error occurred: {req_err}")
            except Exception as e:
                print(f"{pt}://{item['proxy']} request error {e}")
                traceback.print_exc()
                will_break = True
                break
    snapshot = tracemalloc.take_snapshot()
    top_stats = snapshot.compare_to(tracemalloc.take_snapshot(), "lineno")
    # for stat in top_stats[:10]:
    #     print(stat)
