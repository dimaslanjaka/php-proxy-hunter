import re
import shutil
import subprocess
from pathlib import Path

try:
    import lzf
except ImportError as exc:
    raise SystemExit(
        "Missing lzf module.\n"
        "Install first:\n"
        "pip install git+https://github.com/FledgeXu/python-neo-lzf.git"
    ) from exc


# ===== CONFIG =====
OLD_PROXY = "103.157.117.116:8080"
NEW_PROXY = "62.60.149.162:1080"

SRC = Path.home() / "AppData/Roaming/WNR/PSW/proxycache.lzf"
BACKUP = SRC.with_suffix(".lzf.before_replace_test.bak")
# ==================


def kill_proxy_switcher() -> None:
    subprocess.run(
        ["taskkill", "/IM", "ProxySwitcher.exe", "/F"],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        check=False,
    )


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


def extract_proxy_count(data: bytes) -> int:
    proxy_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}:\d{1,5}")
    return len(
        set(x.group().decode("ascii", errors="ignore") for x in proxy_re.finditer(data))
    )


def replace_length_prefixed_proxy(
    data: bytes, old_proxy: str, new_proxy: str
) -> tuple[bytes, int]:
    old_b = old_proxy.encode("ascii")
    new_b = new_proxy.encode("ascii")

    if len(old_b) != len(new_b):
        raise ValueError(
            "For this first safe test, OLD_PROXY and NEW_PROXY must have the same length.\n"
            f"OLD length: {len(old_b)} -> {old_proxy}\n"
            f"NEW length: {len(new_b)} -> {new_proxy}"
        )

    buf = bytearray(data)
    replaced = 0
    start = 0

    while True:
        pos = bytes(buf).find(old_b, start)
        if pos < 0:
            break

        if pos == 0:
            start = pos + 1
            continue

        length_pos = pos - 1
        length_byte = buf[length_pos]

        # Proxy Switcher decompressed record uses 1 byte before string as string length.
        if length_byte != len(old_b):
            start = pos + 1
            continue

        buf[pos : pos + len(old_b)] = new_b
        replaced += 1
        start = pos + len(new_b)

    return bytes(buf), replaced


def main() -> None:
    if not SRC.exists():
        raise SystemExit(f"File not found: {SRC}")

    old_b = OLD_PROXY.encode("ascii")
    new_b = NEW_PROXY.encode("ascii")

    if len(old_b) != len(new_b):
        raise SystemExit(
            "OLD_PROXY and NEW_PROXY must be same length for this safe test.\n"
            f"OLD: {OLD_PROXY} length={len(old_b)}\n"
            f"NEW: {NEW_PROXY} length={len(new_b)}"
        )

    print(f"Source: {SRC}")
    print(f"Old proxy: {OLD_PROXY}")
    print(f"New proxy: {NEW_PROXY}")
    print(f"Proxy length: {len(old_b)}")

    print("Closing ProxySwitcher.exe if running...")
    kill_proxy_switcher()

    raw = SRC.read_bytes()

    if not raw.startswith(b"LZF1"):
        raise SystemExit("Invalid file: missing LZF1 header")

    header = raw[:10]
    payload = raw[10:]

    print(f"Original compressed size: {len(raw):,}")
    print(f"Header: {header.hex(' ')}")

    dec = lzf_decompress_tolerant(payload)
    print(f"Decompressed size: {len(dec):,}")
    print(f"Proxy count before replace: {extract_proxy_count(dec):,}")

    if OLD_PROXY.encode("ascii") not in dec:
        raise SystemExit(f"Old proxy not found in decompressed data: {OLD_PROXY}")

    new_dec, replaced = replace_length_prefixed_proxy(dec, OLD_PROXY, NEW_PROXY)

    if replaced <= 0:
        raise SystemExit(
            "Found old proxy text, but no valid length-prefixed record was replaced."
        )

    print(f"Replaced records: {replaced}")
    print(f"Old proxy still exists: {OLD_PROXY.encode('ascii') in new_dec}")
    print(f"New proxy exists: {NEW_PROXY.encode('ascii') in new_dec}")
    print(f"Proxy count after replace: {extract_proxy_count(new_dec):,}")

    compressed = lzf.compress(new_dec)

    if not compressed:
        raise SystemExit("lzf.compress failed")

    rebuilt = header + compressed

    shutil.copy2(SRC, BACKUP)
    SRC.write_bytes(rebuilt)

    print("")
    print("DONE")
    print(f"Backup: {BACKUP}")
    print(f"Written: {SRC}")
    print(f"New compressed size: {len(rebuilt):,}")
    print("")
    print("Now open Proxy Switcher and search this proxy:")
    print(NEW_PROXY)
    print("")
    print("Restore command if needed:")
    print(f'copy /Y "{BACKUP}" "{SRC}"')


if __name__ == "__main__":
    main()
