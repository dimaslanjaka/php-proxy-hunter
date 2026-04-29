import os
import re


def to_unix_path(path: str) -> str:
    if not path:
        return ""

    # Replace backslashes with forward slashes
    path = path.replace("\\", "/")

    # Collapse multiple slashes into one (except protocol like http://)
    path = re.sub(r"(?<!:)//+", "/", path)

    return path


def path_join(*parts):
    if not parts:
        return ""

    cleaned = []

    for i, part in enumerate(parts):
        if not part:
            continue

        # Normalize slashes
        part = part.replace("\\", os.sep).replace("/", os.sep)

        if i == 0:
            # Keep leading slash/drive for the first part only
            cleaned.append(part.rstrip(os.sep))
        else:
            # Strip leading slashes so it doesn't reset
            cleaned.append(part.strip(os.sep))

    return os.path.normpath(os.sep.join(cleaned))


# Test
if __name__ == "__main__":
    print(path_join("folder", "subfolder", "file.txt"))
    print(path_join("/root", "folder", "/reset", "file.txt"))
    print(path_join("C:\\", "folder", "file.txt"))
    print(to_unix_path(path_join("C:\\", "folder", "file.txt")))
