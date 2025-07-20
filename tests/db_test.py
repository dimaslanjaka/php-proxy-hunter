import os
import sys

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.ProxyDB import ProxyDB

db = ProxyDB()
# select = db.get_working_proxies()

# print(get_random_item_list(select)['proxy'])
db.add("71.86.129.168:8080")
