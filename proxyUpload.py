import json
from src.func_proxy import upload_proxy, is_port_open
from src.ProxyDB import ProxyDB
from src.func import get_relative_path


def upload_all_proxies():
    db = ProxyDB(get_relative_path('src/database.sqlite'))

    data_list = db.get_all_proxies()

    # List comprehension to transform the list of dictionaries into a list of strings
    result = [
        f"{item['proxy']}@{item['username']}:{item['password']}" if item['username'] and item['password']
        else item['proxy']
        for item in data_list
    ]

    # Assuming 'result' is your list of strings that you want to chunk
    chunk_size = 500
    chunked_list = []

    for i in range(0, len(result), chunk_size):
        chunk = result[i:i + chunk_size]
        chunked_list.append(chunk)

    for chunk in chunked_list:
        # open_ports = list(filter(is_port_open, chunk))
        # upload_proxy(json.dumps(open_ports))
        upload_proxy(json.dumps(chunk))


if __name__ == '__main__':
    upload_all_proxies()
