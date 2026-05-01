from pprint import pprint
import sys
import os

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_proxy import *
from src.func import *
from proxy_hunter import extract_proxies
from src.geoPlugin import get_geo_ip

string = """
104.16.10.200:80
"""

proxies = extract_proxies(string)
for data in proxies:
    result = get_geo_ip(data.proxy)
    print(f"{data.proxy} - {result}")
