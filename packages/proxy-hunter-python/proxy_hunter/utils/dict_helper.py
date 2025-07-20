from typing import Dict


def dict_updater(headers: Dict[str, str], updates: Dict[str, str]) -> None:
    """
    Update headers dict with updates, matching keys case-insensitively.
    If a key exists in headers (regardless of case), its value is replaced.
    Otherwise, the new key-value is added.
    Args:
        headers (Dict[str, str]): The original headers dictionary.
        updates (Dict[str, str]): The updates to apply.
    """
    lower_map = {k.lower(): k for k in headers}
    for k, v in updates.items():
        k_lower = k.lower()
        if k_lower in lower_map:
            headers[lower_map[k_lower]] = v
        else:
            headers[k] = v
