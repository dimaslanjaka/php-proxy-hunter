import json
from src.func_proxy import build_request, upload_proxy
from src.ProxyDB import ProxyDB

db = ProxyDB()

data_list = db.get_all_proxies()

# List comprehension to transform the list of dictionaries into a list of strings
result = [
    f"{item['proxy']}@{item['username']}:{item['password']}" if item['username'] and item['password']
    else item['proxy']
    for item in data_list
]

# Assuming 'result' is your list of strings that you want to chunk
chunk_size = 100
chunked_list = []

for i in range(0, len(result), chunk_size):
    chunk = result[i:i + chunk_size]
    chunked_list.append(chunk)

for chunk in chunked_list:
    upload_proxy(json.dumps(chunk))
