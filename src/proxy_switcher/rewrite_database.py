import os
import shutil
import sys
from pathlib import Path

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

try:
    import lzf
except ImportError as exc:
    raise SystemExit(
        "Install first:\n"
        "pip install git+https://github.com/FledgeXu/python-neo-lzf.git"
    ) from exc


src = Path.home() / "AppData/Roaming/WNR/PSW/proxycache.lzf"
backup = src.with_suffix(".lzf.before_roundtrip.bak")
test_out = src.with_name("proxycache.roundtrip.lzf")


def lzf_decompress_tolerant(data: bytes, max_out: int = 300_000_000) -> bytes:
    i = 0
    out = bytearray()

    while i < len(data):
        ctrl = data[i]
        i += 1

        if ctrl < 32:
            length = ctrl + 1
            if i + length > len(data):
                break
            out.extend(data[i : i + length])
            i += length
        else:
            length = ctrl >> 5
            ref_offset = (ctrl & 0x1F) << 8

            if length == 7:
                if i >= len(data):
                    break
                length += data[i]
                i += 1

            if i >= len(data):
                break

            ref_offset += data[i]
            i += 1

            ref = len(out) - ref_offset - 1
            length += 2

            if ref < 0:
                break

            for _ in range(length):
                out.append(out[ref])
                ref += 1

            if len(out) > max_out:
                break

    return bytes(out)


raw = src.read_bytes()

if not raw.startswith(b"LZF1"):
    raise SystemExit("Not a Proxy Switcher LZF1 file")

header = raw[:10]
payload = raw[10:]

print(f"Original: {src}")
print(f"Original size: {len(raw):,}")
print(f"Header: {header.hex(' ')}")

dec = lzf_decompress_tolerant(payload)
print(f"Decompressed size: {len(dec):,}")

compressed = lzf.compress(dec)

if not compressed:
    raise SystemExit("lzf.compress returned empty result")

rebuilt = header + compressed

shutil.copy2(src, backup)
test_out.write_bytes(rebuilt)

print(f"Backup written: {backup}")
print(f"Roundtrip file written: {test_out}")
print(f"Roundtrip size: {len(rebuilt):,}")
print()
print("Manual test:")
print("1. Close Proxy Switcher completely")
print(f"2. Rename original: {src.name} -> proxycache.original.lzf")
print(f"3. Rename test file: {test_out.name} -> proxycache.lzf")
print("4. Open Proxy Switcher and check if proxy list loads")
