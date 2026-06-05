from __future__ import annotations

import os
import shutil
import sys
from pathlib import Path
from typing import Iterator

import pytest

try:
    import lzf
except ImportError:
    pytest.skip(
        "lzf module not installed. Install: "
        "pip install git+https://github.com/FledgeXu/python-neo-lzf.git",
        allow_module_level=True,
    )


PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "../.."))

if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)


from src.func import get_relative_path  # noqa: E402
from src.proxy_switcher.psw_lzf import (  # noqa: E402
    DEFAULT_PAYLOAD_OFFSET,
    DEFAULT_PROXYCACHE,
    extract_proxies,
    lzf_decompress_tolerant,
    modify_lzf,
    read_lzf,
    replace_proxy_same_length,
    update_lzf,
    write_extracted_proxies,
)

HEADER = b"LZF1\xff\xff\x00\x00\x60\x27"

PROXY_DIR_RELATIVE = "tmp/proxies"
STAGING_CACHE_FILENAME = "staging_proxycache.lzf"
DESTINATION_CACHE_FILENAME = "test_proxycache.lzf"
EXTRACTED_PROXY_FILENAME = "test_extracted_proxies.txt"


def make_proxy_record(
    proxy: str,
    prefix: bytes = b"\x00" * 16,
    suffix: bytes = b"\x00" * 16,
) -> bytes:
    proxy_bytes = proxy.encode("ascii")
    assert len(proxy_bytes) <= 255

    return prefix + bytes([len(proxy_bytes)]) + proxy_bytes + suffix


def make_cache_file(path: Path, decompressed: bytes) -> Path:
    compressed = lzf.compress(decompressed)

    if not compressed:
        decompressed = decompressed + (b"\x00" * 1024)
        compressed = lzf.compress(decompressed)

    assert compressed

    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(HEADER + compressed)

    return path


def stage_then_copy(staging: Path, destination: Path) -> Path:
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(staging, destination)

    return destination


def _safe_unlink(path: Path) -> None:
    if path.exists():
        try:
            path.unlink()
        except OSError:
            pass


def _cleanup_test_files(base: Path) -> None:
    direct_files = [
        base / STAGING_CACHE_FILENAME,
        base / DESTINATION_CACHE_FILENAME,
        base / EXTRACTED_PROXY_FILENAME,
    ]

    for path in direct_files:
        _safe_unlink(path)

    for backup in base.glob(f"{DESTINATION_CACHE_FILENAME}.bak_*"):
        _safe_unlink(backup)

    for tmp_file in base.glob(f"{DESTINATION_CACHE_FILENAME}*.tmp"):
        _safe_unlink(tmp_file)


@pytest.fixture
def proxycache_path() -> Iterator[tuple[Path, Path]]:
    base = Path(get_relative_path(PROXY_DIR_RELATIVE))
    base.mkdir(parents=True, exist_ok=True)

    staging = base / STAGING_CACHE_FILENAME
    destination = base / DESTINATION_CACHE_FILENAME

    _cleanup_test_files(base)

    try:
        yield staging, destination
    finally:
        _cleanup_test_files(base)


def test_read_lzf_decompresses_proxycache(proxycache_path: tuple[Path, Path]) -> None:
    staging, destination = proxycache_path

    decompressed = (
        b"ProxySwitcher\x00"
        + make_proxy_record("62.60.149.161:1080")
        + make_proxy_record("147.45.75.124:8080")
    )

    make_cache_file(staging, decompressed)
    stage_then_copy(staging, destination)

    cache = read_lzf(destination)

    assert cache.path == destination
    assert cache.raw.startswith(b"LZF1")
    assert cache.header == HEADER
    assert cache.payload_offset == DEFAULT_PAYLOAD_OFFSET
    assert b"62.60.149.161:1080" in cache.decompressed
    assert b"147.45.75.124:8080" in cache.decompressed

    proxies = extract_proxies(cache.decompressed)

    assert proxies == [
        "147.45.75.124:8080",
        "62.60.149.161:1080",
    ]


def test_read_lzf_rejects_missing_file(proxycache_path: tuple[Path, Path]) -> None:
    _staging, destination = proxycache_path

    assert not destination.exists()

    with pytest.raises(FileNotFoundError):
        read_lzf(destination)


def test_read_lzf_rejects_invalid_header(proxycache_path: tuple[Path, Path]) -> None:
    staging, destination = proxycache_path

    staging.write_bytes(b"NOPE" + b"\x00" * 20)
    stage_then_copy(staging, destination)

    with pytest.raises(ValueError, match="missing LZF1 header"):
        read_lzf(destination)


def test_extract_proxies_from_decompressed_bytes() -> None:
    data = b"""
    62.60.149.161:1080
    147.45.75.124:8080
    62.60.149.161:1080
    """

    proxies = extract_proxies(data)

    assert proxies == [
        "147.45.75.124:8080",
        "62.60.149.161:1080",
    ]


def test_update_lzf_rewrites_file_and_creates_backup(
    proxycache_path: tuple[Path, Path],
) -> None:
    staging, destination = proxycache_path

    original_dec = make_proxy_record("62.60.149.161:1080")
    new_dec = make_proxy_record("62.60.149.162:1080")

    make_cache_file(staging, original_dec)
    stage_then_copy(staging, destination)

    backup_path = update_lzf(
        new_dec,
        path=destination,
        backup=True,
        close_app=False,
    )

    assert backup_path is not None
    assert backup_path.exists()
    assert backup_path.read_bytes().startswith(b"LZF1")

    cache = read_lzf(destination)

    assert b"62.60.149.162:1080" in cache.decompressed
    assert b"62.60.149.161:1080" not in cache.decompressed


def test_update_lzf_without_backup(proxycache_path: tuple[Path, Path]) -> None:
    staging, destination = proxycache_path

    make_cache_file(staging, make_proxy_record("62.60.149.161:1080"))
    stage_then_copy(staging, destination)

    backup_path = update_lzf(
        make_proxy_record("62.60.149.162:1080"),
        path=destination,
        backup=False,
        close_app=False,
    )

    assert backup_path is None

    cache = read_lzf(destination)

    assert b"62.60.149.162:1080" in cache.decompressed
    assert b"62.60.149.161:1080" not in cache.decompressed


def test_modify_lzf_mutates_decompressed_payload(
    proxycache_path: tuple[Path, Path],
) -> None:
    staging, destination = proxycache_path

    make_cache_file(staging, make_proxy_record("62.60.149.161:1080"))
    stage_then_copy(staging, destination)

    def patch(buf: bytearray) -> None:
        old = b"62.60.149.161:1080"
        new = b"62.60.149.162:1080"

        pos = buf.find(old)
        assert pos >= 0

        buf[pos : pos + len(old)] = new

    modify_lzf(
        patch,
        path=destination,
        backup=False,
        close_app=False,
    )

    cache = read_lzf(destination)

    assert b"62.60.149.162:1080" in cache.decompressed
    assert b"62.60.149.161:1080" not in cache.decompressed


def test_replace_proxy_same_length(proxycache_path: tuple[Path, Path]) -> None:
    staging, destination = proxycache_path

    decompressed = make_proxy_record("62.60.149.161:1080") + make_proxy_record(
        "147.45.75.124:8080"
    )

    make_cache_file(staging, decompressed)
    stage_then_copy(staging, destination)

    count = replace_proxy_same_length(
        "62.60.149.161:1080",
        "62.60.149.162:1080",
        path=destination,
        backup=False,
        close_app=False,
    )

    assert count == 1

    cache = read_lzf(destination)

    assert b"62.60.149.162:1080" in cache.decompressed
    assert b"62.60.149.161:1080" not in cache.decompressed
    assert b"147.45.75.124:8080" in cache.decompressed


def test_replace_proxy_same_length_rejects_different_length(
    proxycache_path: tuple[Path, Path],
) -> None:
    staging, destination = proxycache_path

    make_cache_file(staging, make_proxy_record("62.60.149.161:1080"))
    stage_then_copy(staging, destination)

    with pytest.raises(ValueError, match="same length"):
        replace_proxy_same_length(
            "62.60.149.161:1080",
            "1.2.3.4:80",
            path=destination,
            backup=False,
            close_app=False,
        )


def test_replace_proxy_ignores_non_length_prefixed_match(
    proxycache_path: tuple[Path, Path],
) -> None:
    staging, destination = proxycache_path

    decompressed = b"\x00WRONG_PREFIX" + b"62.60.149.161:1080"

    make_cache_file(staging, decompressed)
    stage_then_copy(staging, destination)

    count = replace_proxy_same_length(
        "62.60.149.161:1080",
        "62.60.149.162:1080",
        path=destination,
        backup=False,
        close_app=False,
    )

    assert count == 0

    cache = read_lzf(destination)

    assert b"62.60.149.161:1080" in cache.decompressed
    assert b"62.60.149.162:1080" not in cache.decompressed


def test_write_extracted_proxies(proxycache_path: tuple[Path, Path]) -> None:
    staging, destination = proxycache_path

    base = Path(get_relative_path(PROXY_DIR_RELATIVE))
    output_file = base / EXTRACTED_PROXY_FILENAME
    _safe_unlink(output_file)

    decompressed = make_proxy_record("62.60.149.161:1080") + make_proxy_record(
        "147.45.75.124:8080"
    )

    make_cache_file(staging, decompressed)
    stage_then_copy(staging, destination)

    try:
        count = write_extracted_proxies(output_file, path=destination)

        assert count == 2
        assert output_file.exists()
        assert output_file.read_text(encoding="utf-8").splitlines() == [
            "147.45.75.124:8080",
            "62.60.149.161:1080",
        ]
    finally:
        _safe_unlink(output_file)


def test_tolerant_decompress_returns_bytes() -> None:
    original = make_proxy_record("62.60.149.161:1080")
    compressed = lzf.compress(original)

    assert compressed

    dec, error = lzf_decompress_tolerant(compressed)

    assert isinstance(dec, bytes)
    assert b"62.60.149.161:1080" in dec
    assert error is None


def test_tolerant_decompress_returns_partial_on_truncated_payload() -> None:
    original = make_proxy_record("62.60.149.161:1080")
    compressed = lzf.compress(original)

    assert compressed

    truncated = compressed[:-3]
    dec, error = lzf_decompress_tolerant(truncated)

    assert isinstance(dec, bytes)
    assert error is None or isinstance(error, str)


def test_real_proxycache_copy_decode_and_append_proxy() -> None:
    real_cache = Path(DEFAULT_PROXYCACHE)

    if not real_cache.exists():
        pytest.skip(f"Real Proxy Switcher cache not found: {real_cache}")

    base = Path(get_relative_path(PROXY_DIR_RELATIVE))
    base.mkdir(parents=True, exist_ok=True)

    copied_cache = base / "real_proxycache_copy_test.lzf"
    _safe_unlink(copied_cache)

    test_proxy = "203.0.113.10:5555"

    try:
        shutil.copy2(real_cache, copied_cache)

        cache_before = read_lzf(copied_cache)
        proxies_before = extract_proxies(cache_before.decompressed)

        assert cache_before.raw.startswith(b"LZF1")
        assert len(cache_before.decompressed) > 0
        assert len(proxies_before) > 0

        proxy_bytes = test_proxy.encode("ascii")
        appended_record = (
            b"\x00" * 16 + bytes([len(proxy_bytes)]) + proxy_bytes + b"\x00" * 16
        )

        new_decompressed = cache_before.decompressed + appended_record

        update_lzf(
            new_decompressed,
            path=copied_cache,
            backup=False,
            close_app=False,
        )

        cache_after = read_lzf(copied_cache)
        proxies_after = extract_proxies(cache_after.decompressed)

        assert test_proxy in proxies_after
        assert len(proxies_after) >= len(proxies_before)

    finally:
        _safe_unlink(copied_cache)


if __name__ == "__main__":
    raise SystemExit(pytest.main([__file__, "-v"]))
