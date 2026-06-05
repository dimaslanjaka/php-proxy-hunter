from pathlib import Path
import re

dec = Path(
    r"D:\Repositories\php-proxy-hunter\tmp\proxies\proxycache_decompressed.bin"
).read_bytes()

targets = [
    b"62.60.149.161",
    b"154.65.39.7",
    b"My Proxy Servers",
]

for target in targets:
    pos = dec.find(target)
    print("=" * 80)
    print(target, pos)

    if pos == -1:
        continue

    start = max(0, pos - 256)
    end = min(len(dec), pos + 512)
    chunk = dec[start:end]

    print("HEX:")
    print(chunk.hex(" "))

    print("\nASCII:")
    print("".join(chr(b) if 32 <= b <= 126 else "." for b in chunk))
