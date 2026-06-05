import os
import re
import sys
import struct
from pathlib import Path

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from func import get_relative_path

try:
    import lzf
except ImportError as exc:
    raise SystemExit(
        "Install first:\n"
        "pip install git+https://github.com/FledgeXu/python-neo-lzf.git"
    ) from exc


src = Path.home() / "AppData/Roaming/WNR/PSW/proxycache.lzf"
out_dir = Path(get_relative_path("tmp/proxies"))
out_dir.mkdir(parents=True, exist_ok=True)

out_txt = out_dir / "proxycache_extracted_proxies_advanced.txt"
out_dec = out_dir / "proxycache_decompressed_blocks.bin"

ip_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}")
proxy_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}:\d{1,5}")
num_re = re.compile(rb"\b\d{2,5}\b")


def valid_ip(ip: str) -> bool:
    parts = ip.split(".")
    if len(parts) != 4:
        return False
    try:
        return all(0 <= int(x) <= 255 for x in parts)
    except ValueError:
        return False


def valid_port(port: str) -> bool:
    try:
        n = int(port)
        return 1 <= n <= 65535
    except ValueError:
        return False


def extract_text_proxies(data: bytes) -> set[str]:
    found = set()

    for m in proxy_re.finditer(data):
        value = m.group().decode("ascii", errors="ignore")
        ip, port = value.split(":", 1)
        if valid_ip(ip) and valid_port(port):
            found.add(value)

    return found


def extract_ip_near_port(data: bytes, window: int = 80) -> set[str]:
    found = set()

    for m in ip_re.finditer(data):
        ip = m.group().decode("ascii", errors="ignore")
        if not valid_ip(ip):
            continue

        start = max(0, m.end())
        end = min(len(data), m.end() + window)
        nearby = data[start:end]

        for p in num_re.finditer(nearby):
            port = p.group().decode("ascii", errors="ignore")
            if valid_port(port):
                found.add(f"{ip}:{port}")
                break

    return found


def try_lzf(data: bytes, expected_size: int) -> bytes | None:
    try:
        dec = lzf.decompress(data, expected_size)
        if dec and isinstance(dec, bytes):
            return dec
    except Exception:
        return None
    return None


def scan_lzf_blocks(raw: bytes) -> list[bytes]:
    """
    Proxy Switcher likely uses custom block records, not one whole LZF stream.
    This scans for common block layouts:
      [compressed_size:u32][decompressed_size:u32][data]
      [decompressed_size:u32][compressed_size:u32][data]
      [compressed_size:u16][decompressed_size:u16][data]
      [decompressed_size:u16][compressed_size:u16][data]
    little-endian and big-endian.
    """
    blocks = []
    seen = set()
    n = len(raw)

    patterns = [
        ("<II", 8, 0, 1),
        ("<II", 8, 1, 0),
        (">II", 8, 0, 1),
        (">II", 8, 1, 0),
        ("<HH", 4, 0, 1),
        ("<HH", 4, 1, 0),
        (">HH", 4, 0, 1),
        (">HH", 4, 1, 0),
    ]

    for pos in range(0, n - 12):
        for fmt, header_size, cidx, uidx in patterns:
            size = struct.calcsize(fmt)

            try:
                values = struct.unpack_from(fmt, raw, pos)
            except struct.error:
                continue

            comp_size = values[cidx]
            uncomp_size = values[uidx]

            if not (8 <= comp_size <= 2_000_000):
                continue

            if not (16 <= uncomp_size <= 20_000_000):
                continue

            if comp_size > uncomp_size:
                continue

            data_start = pos + header_size
            data_end = data_start + comp_size

            if data_end > n:
                continue

            key = (data_start, comp_size, uncomp_size)
            if key in seen:
                continue
            seen.add(key)

            dec = try_lzf(raw[data_start:data_end], uncomp_size)
            if not dec:
                continue

            hits = extract_text_proxies(dec) | extract_ip_near_port(dec)

            if hits:
                print(
                    f"LZF block hit: pos={pos}, data={data_start}:{data_end}, "
                    f"compressed={comp_size:,}, decompressed={uncomp_size:,}, hits={len(hits):,}"
                )
                blocks.append(dec)

    return blocks


def main() -> None:
    raw = src.read_bytes()

    print(f"File: {src}")
    print(f"Size: {len(raw):,} bytes")

    all_proxies = set()

    raw_direct = extract_text_proxies(raw)
    raw_near = extract_ip_near_port(raw)

    print(f"Raw ip:port hits: {len(raw_direct):,}")
    print(f"Raw ip + nearby port hits: {len(raw_near):,}")

    all_proxies |= raw_direct
    all_proxies |= raw_near

    print("Scanning possible LZF blocks...")
    blocks = scan_lzf_blocks(raw)

    if blocks:
        joined = b"\n---BLOCK---\n".join(blocks)
        out_dec.write_bytes(joined)

        for block in blocks:
            all_proxies |= extract_text_proxies(block)
            all_proxies |= extract_ip_near_port(block)

    cleaned = sorted(
        p
        for p in all_proxies
        if ":" in p and valid_ip(p.split(":", 1)[0]) and valid_port(p.split(":", 1)[1])
    )

    out_txt.write_text("\n".join(cleaned), encoding="utf-8")

    print()
    print(f"Total proxies extracted: {len(cleaned):,}")
    print(f"Saved: {out_txt}")

    if blocks:
        print(f"Saved decompressed blocks: {out_dec}")
    else:
        print(
            "No LZF block headers found. Need inspect file header/record format next."
        )


if __name__ == "__main__":
    main()
