import re
from pathlib import Path

src = Path.home() / "AppData/Roaming/WNR/PSW/proxycache.lzf"

ip_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}")
proxy_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}(?::\d{1,5})?")

raw = src.read_bytes()

print(f"File: {src}")
print(f"Size: {len(raw):,} bytes")
print()

print("First 256 bytes HEX:")
print(raw[:256].hex(" "))
print()

print("First 256 bytes ASCII-ish:")
print("".join(chr(b) if 32 <= b <= 126 else "." for b in raw[:256]))
print()

hits = list(proxy_re.finditer(raw))
print(f"Raw proxy/IP hits: {len(hits):,}")
print()

for i, m in enumerate(hits[:20], 1):
    start = max(0, m.start() - 80)
    end = min(len(raw), m.end() + 120)
    chunk = raw[start:end]

    print("=" * 80)
    print(f"HIT {i}: offset={m.start()} value={m.group().decode(errors='ignore')}")
    print("HEX:")
    print(chunk.hex(" "))
    print("ASCII:")
    print("".join(chr(b) if 32 <= b <= 126 else "." for b in chunk))
