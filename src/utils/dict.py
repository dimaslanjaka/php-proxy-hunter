def ensure_str_dict(d: dict) -> dict:
    """
    Converts all keys and values in a dictionary to strings.

    If a value is None, it will be replaced with an empty string ("").
    This is useful when preparing headers or data for HTTP requests,
    which require string-type keys and values.

    Args:
        d (dict): The input dictionary to convert.

    Returns:
        dict: A new dictionary with all keys and values as strings.
    """
    return {str(k): str(v) if v is not None else "" for k, v in d.items()}
