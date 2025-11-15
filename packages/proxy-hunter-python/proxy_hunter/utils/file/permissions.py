import os
import stat


def fix_permissions(
    path: str, desired_permissions: int = stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO
) -> None:
    """Set permissions on a path. Safe wrapper around os.chmod.

    Args:
        path: filesystem path
        desired_permissions: permission bits
    """
    try:
        os.chmod(path, desired_permissions)
    except OSError as e:
        print(f"Error fix perm {path}: {e}")
