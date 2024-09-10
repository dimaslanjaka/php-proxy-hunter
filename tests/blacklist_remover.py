import os
import sys

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import *
from src.func_proxy import blacklist_remover

if __name__ == "__main__":
    blacklist_remover(
        get_relative_path("src/database.sqlite"),
        get_relative_path("data/blacklist.conf"),
    )
