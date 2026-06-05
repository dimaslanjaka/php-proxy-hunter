import os
import re
import sys
from pathlib import Path

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from func import get_relative_path

src = Path.home() / "AppData/Roaming/WNR/PSW/proxycache.lzf"

out_dir = Path(get_relative_path("tmp/proxies"))
out_dir.mkdir(parents=True, exist_ok=True)

out_txt = out_dir / "proxycache_extracted_proxies.txt"
out_dec = out_dir / "proxycache_decompressed.bin"

proxy_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}:\d{1,5}")
ip_re = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}")


def valid_proxy(value: str) -> bool:
    if ":" not in value:
        return False

    ip, port = value.rsplit(":", 1)

    try:
        if not all(0 <= int(x) <= 255 for x in ip.split(".")):
            return False

        p = int(port)
        return 1 <= p <= 65535
    except Exception:
        return False


def extract_proxies(data: bytes) -> set[str]:
    found = set()

    for m in proxy_re.finditer(data):
        value = m.group().decode("ascii", errors="ignore")
        if valid_proxy(value):
            found.add(value)

    return found


def lzf_decompress_tolerant(
    data: bytes, max_out: int = 300_000_000
) -> tuple[bytes, str | None]:
    """
    Standard LZF decoder, but returns partial output instead of throwing everything away.
    Useful for Proxy Switcher .lzf because the file may have custom header/footer.
    """
    i = 0
    out = bytearray()

    try:
        while i < len(data):
            ctrl = data[i]
            i += 1

            if ctrl < 32:
                length = ctrl + 1

                if i + length > len(data):
                    return bytes(out), f"literal out of range at input offset {i}"

                out.extend(data[i : i + length])
                i += length
            else:
                length = ctrl >> 5
                ref_offset = (ctrl & 0x1F) << 8

                if length == 7:
                    if i >= len(data):
                        return bytes(out), f"length out of range at input offset {i}"
                    length += data[i]
                    i += 1

                if i >= len(data):
                    return bytes(out), f"offset out of range at input offset {i}"

                ref_offset += data[i]
                i += 1

                ref = len(out) - ref_offset - 1
                length += 2

                if ref < 0:
                    return bytes(out), (
                        f"bad back reference at input offset {i}, "
                        f"out={len(out)}, ref_offset={ref_offset}"
                    )

                for _ in range(length):
                    out.append(out[ref])
                    ref += 1

                if len(out) > max_out:
                    return bytes(out), "max output exceeded"

        return bytes(out), None

    except Exception as e:
        return bytes(out), str(e)


def main() -> None:
    raw = src.read_bytes()

    print(f"File: {src}")
    print(f"Size: {len(raw):,} bytes")
    print(f"Header: {raw[:16].hex(' ')}")

    raw_hits = extract_proxies(raw)
    print(f"Raw ip:port hits: {len(raw_hits):,}")

    candidates = []

    # Proxy Switcher file begins with LZF1 + custom metadata.
    # Try small offsets and keep the result with the most proxy hits.
    for offset in range(0, 64):
        dec, error = lzf_decompress_tolerant(raw[offset:])
        hits = extract_proxies(dec)

        if len(hits) > 0:
            candidates.append((len(hits), offset, len(dec), error, dec, hits))
            print(
                f"offset={offset:02d} hits={len(hits):,} "
                f"decompressed={len(dec):,} error={error}"
            )

    if not candidates:
        print("No decompression candidate found.")
        cleaned = sorted(raw_hits)
        out_txt.write_text("\n".join(cleaned), encoding="utf-8")
        print(f"Saved raw only: {out_txt}")
        return

    candidates.sort(reverse=True, key=lambda x: x[0])
    best_hits_count, best_offset, best_len, best_error, best_dec, best_hits = (
        candidates[0]
    )

    all_hits = set()
    all_hits.update(raw_hits)
    all_hits.update(best_hits)

    cleaned = sorted(x for x in all_hits if valid_proxy(x))

    out_dec.write_bytes(best_dec)
    out_txt.write_text("\n".join(cleaned), encoding="utf-8")

    print()
    print(f"Best offset: {best_offset}")
    print(f"Best decompressed size: {best_len:,}")
    print(f"Best decompression error: {best_error}")
    print(f"Total proxies extracted: {len(cleaned):,}")
    print(f"Saved proxies: {out_txt}")
    print(f"Saved decompressed: {out_dec}")


if __name__ == "__main__":
    main()
