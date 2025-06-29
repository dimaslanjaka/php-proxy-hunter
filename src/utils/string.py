import shlex


def escapeshellarg(arg):
    """
    Escapes a single string to be safely used as a shell argument,
    similar to PHP's escapeshellarg().
    """
    return shlex.quote(arg)
