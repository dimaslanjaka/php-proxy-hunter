from __future__ import annotations

import re
import shutil
import subprocess
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Callable

try:
    import lzf
except ImportError as exc:
    raise SystemExit(
        "Missing lzf module.\n"
        "Install first:\n"
        "pip install git+https://github.com/FledgeXu/python-neo-lzf.git"
    ) from exc

from proxy_hunter import extract_proxies as ph_extract_proxies, is_valid_proxy

DEFAULT_PROXYCACHE = Path.home() / "AppData/Roaming/WNR/PSW/proxycache.lzf"
DEFAULT_PAYLOAD_OFFSET = 10

PROXY_RE = re.compile(rb"(?:\d{1,3}\.){3}\d{1,3}:\d{1,5}")


@dataclass
class ProxySwitcherLZF:
    path: Path
    raw: bytes
    header: bytes
    payload: bytes
    decompressed: bytes
    payload_offset: int = DEFAULT_PAYLOAD_OFFSET
    decode_error: str | None = None


def kill_proxy_switcher() -> None:
    subprocess.run(
        ["taskkill", "/IM", "ProxySwitcher.exe", "/F"],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        check=False,
    )


def lzf_decompress_tolerant(
    data: bytes, max_out: int = 300_000_000
) -> tuple[bytes, str | None]:
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

    except Exception as exc:
        return bytes(out), str(exc)


def extract_proxies(data: bytes) -> list[str]:
    """Extract proxies from bytes data using proxy_hunter's extract_proxies."""
    text = data.decode("ascii", errors="ignore")
    proxy_objects = ph_extract_proxies(text)
    return sorted({p.proxy for p in proxy_objects})


def read_lzf(
    path: str | Path = DEFAULT_PROXYCACHE,
    payload_offset: int = DEFAULT_PAYLOAD_OFFSET,
) -> ProxySwitcherLZF:
    path = Path(path)

    if not path.exists():
        raise FileNotFoundError(path)

    raw = path.read_bytes()

    if not raw.startswith(b"LZF1"):
        raise ValueError(f"Invalid Proxy Switcher cache, missing LZF1 header: {path}")

    header = raw[:payload_offset]
    payload = raw[payload_offset:]

    decompressed, error = lzf_decompress_tolerant(payload)

    if not decompressed:
        raise ValueError(f"Failed to decompress payload: {path}")

    return ProxySwitcherLZF(
        path=path,
        raw=raw,
        header=header,
        payload=payload,
        decompressed=decompressed,
        payload_offset=payload_offset,
        decode_error=error,
    )


def backup_lzf(path: str | Path = DEFAULT_PROXYCACHE) -> Path:
    path = Path(path)

    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    backup = path.with_name(f"{path.name}.bak_{stamp}")

    shutil.copy2(path, backup)
    return backup


def update_lzf(
    new_decompressed: bytes,
    path: str | Path = DEFAULT_PROXYCACHE,
    payload_offset: int = DEFAULT_PAYLOAD_OFFSET,
    backup: bool = True,
    close_app: bool = True,
) -> Path | None:
    path = Path(path)

    if close_app:
        kill_proxy_switcher()

    current = read_lzf(path, payload_offset=payload_offset)

    backup_path: Path | None = None
    if backup:
        backup_path = backup_lzf(path)

    compressed = lzf.compress(new_decompressed)

    if not compressed:
        raise ValueError("lzf.compress() failed")

    rebuilt = current.header + compressed

    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_bytes(rebuilt)
    tmp.replace(path)

    return backup_path


def modify_lzf(
    mutator: Callable[[bytearray], bytes | bytearray | None],
    path: str | Path = DEFAULT_PROXYCACHE,
    payload_offset: int = DEFAULT_PAYLOAD_OFFSET,
    backup: bool = True,
    close_app: bool = True,
) -> Path | None:
    cache = read_lzf(path, payload_offset=payload_offset)

    buf = bytearray(cache.decompressed)
    result = mutator(buf)

    if result is None:
        new_decompressed = bytes(buf)
    else:
        new_decompressed = bytes(result)

    return update_lzf(
        new_decompressed,
        path=path,
        payload_offset=payload_offset,
        backup=backup,
        close_app=close_app,
    )


def replace_proxy_same_length(
    old_proxy: str,
    new_proxy: str,
    path: str | Path = DEFAULT_PROXYCACHE,
    payload_offset: int = DEFAULT_PAYLOAD_OFFSET,
    backup: bool = True,
    close_app: bool = True,
) -> int:
    old_b = old_proxy.encode("ascii")
    new_b = new_proxy.encode("ascii")

    if len(old_b) != len(new_b):
        raise ValueError(
            "old_proxy and new_proxy must have same length for safe replacement. "
            f"old={len(old_b)}, new={len(new_b)}"
        )

    replaced_count = 0

    def mutator(buf: bytearray) -> None:
        nonlocal replaced_count

        start = 0
        while True:
            pos = bytes(buf).find(old_b, start)
            if pos < 1:
                break

            # Proxy string appears to be length-prefixed by 1 byte.
            if buf[pos - 1] == len(old_b):
                buf[pos : pos + len(old_b)] = new_b
                replaced_count += 1
                start = pos + len(new_b)
            else:
                start = pos + 1

    modify_lzf(
        mutator,
        path=path,
        payload_offset=payload_offset,
        backup=backup,
        close_app=close_app,
    )

    return replaced_count


def write_extracted_proxies(
    output_file: str | Path,
    path: str | Path = DEFAULT_PROXYCACHE,
    payload_offset: int = DEFAULT_PAYLOAD_OFFSET,
) -> int:
    cache = read_lzf(path, payload_offset=payload_offset)
    proxies = extract_proxies(cache.decompressed)

    output_file = Path(output_file)
    output_file.parent.mkdir(parents=True, exist_ok=True)
    output_file.write_text("\n".join(proxies), encoding="utf-8")

    return len(proxies)
