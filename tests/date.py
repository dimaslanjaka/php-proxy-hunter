from pprint import pprint
import sys
import os

# Add parent directory to the Python path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func_date import *

print(get_current_rfc3339_time())
