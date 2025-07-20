import sys, os

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import *
from src.ProxyDB import ProxyDB

db = ProxyDB()
proxies = db.extract_proxies(
    "35.163.85.149:80 port closed\
http://23.59.124.221:80@x:xpass\
                             http://23.59.124.221:80"
)

for proxy in proxies:
    print(proxy)
