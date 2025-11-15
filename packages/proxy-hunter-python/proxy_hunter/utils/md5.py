"""Utilities for computing MD5 hashes.

This module provides a small, well-documented wrapper around hashlib.md5
with proper type hints and a concise docstring for Sphinx/pydoc.
"""

import hashlib

from typing import Union


def md5(input_string: Union[str, bytes]) -> str:
    """
    Compute the MD5 hex digest for the given input.

    Args:
        input_string: Input data to hash. If a string is provided it will be
            encoded using UTF-8. Bytes are accepted as-is.

    Returns:
        The lowercase hexadecimal MD5 digest as a string.

    Examples:
        >>> md5('hello')
        '5d41402abc4b2a76b9719d911017c592'
    """
    if isinstance(input_string, str):
        input_bytes = input_string.encode("utf-8")
    else:
        input_bytes = input_string

    return hashlib.md5(input_bytes).hexdigest()
