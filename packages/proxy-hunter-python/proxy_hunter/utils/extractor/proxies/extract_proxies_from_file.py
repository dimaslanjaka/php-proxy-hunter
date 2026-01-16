from typing import List
from proxy_hunter.Proxy import Proxy
from .extract_proxies import extract_proxies


def extract_proxies_from_file(filename: str) -> List[Proxy]:
    """
    Read a file containing IP:PORT pairs and parse them.

    Args:
        filename (str): The path to the file.

    Returns:
        List[Proxy]: A list of parsed IP:PORT pairs.
    """
    proxies = []
    try:
        with open(filename, "r", encoding="utf-8") as file:
            for line in file:
                proxies.extend(extract_proxies(line))
    except Exception as e:
        print(f"fail open {filename} {str(e)}")
        pass
    return proxies
