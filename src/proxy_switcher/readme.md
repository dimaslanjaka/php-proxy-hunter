# Proxy Switcher LZF Tools

Utilities for reading, decoding, backing up, rewriting, and extracting proxies from the local cache used by **Proxy Switcher / ProxySwitcher.exe**.

Official website:

```text
https://www.proxyswitcher.com/
````

## About ProxySwitcher.exe

`ProxySwitcher.exe` is the Windows desktop application installed by Proxy Switcher Standard.

Typical install path:

```text
C:\Program Files (x86)\Proxy Switcher Standard\ProxySwitcher.exe
```

The proxy list is not stored beside the executable. The active proxy cache is stored in the user profile under:

```text
%APPDATA%\WNR\PSW\proxycache.lzf
```

Example resolved path:

```text
C:\Users\<User>\AppData\Roaming\WNR\PSW\proxycache.lzf
```

Backup/cache-related files may also exist in the same folder:

```text
proxycache.lzf
proxycache.bak
psw.lz
psw.lz.bak
script.lz
script.lz.bak
passwords.lz
passwords.lz.bak
proxynet.lz
```

## What this module does

This package provides helpers for working with Proxy Switcher cache files:

* Read `proxycache.lzf`
* Decode the LZF-compressed payload
* Extract proxies from decompressed cache data
* Create backups before rewriting
* Recompress and update the `.lzf` cache
* Modify decompressed bytes through a safe callback
* Replace same-length proxy strings inside length-prefixed records
* Write extracted proxies to a text file

Main helper module:

```text
src/proxy_switcher/psw_lzf.py
```

## Dependency

This module uses `python-neo-lzf`.

Install manually:

```bash
pip install git+https://github.com/FledgeXu/python-neo-lzf.git
```

Or add to `requirements-base.txt` / `requirements.txt`:

```text
python-neo-lzf @ git+https://github.com/FledgeXu/python-neo-lzf.git
```

## File format notes

The Proxy Switcher cache file starts with:

```text
LZF1
```

The working payload offset found during testing is:

```python
DEFAULT_PAYLOAD_OFFSET = 10
```

Observed structure:

```text
[10-byte LZF1/custom header][LZF-compressed payload]
```

The decompressed data contains binary records. Proxy strings are visible as length-prefixed ASCII fields, for example:

```text
12 62 2e 60 2e 149 2e 161 3a 1080
```

Conceptually:

```text
[length byte][ip:port string]
```

Example:

```text
18 + 62.60.149.161:1080
```

Do not append raw `ip:port` text directly to `proxycache.lzf`. Always decode, modify the decompressed payload, then recompress and rewrite.

## Safety rules

Always close Proxy Switcher before rewriting the cache:

```cmd
taskkill /IM ProxySwitcher.exe /F
```

Always keep backups before modifying the real cache.

Recommended backup:

```cmd
xcopy "%APPDATA%\WNR\PSW" "%USERPROFILE%\Desktop\PSW_backup\" /E /I /H /Y
```

The helper function `update_lzf(..., backup=True)` also creates timestamped backups automatically.

## Basic usage

### Read and decode cache

```python
from src.proxy_switcher.psw_lzf import read_lzf, extract_proxies

cache = read_lzf()

print(cache.path)
print(len(cache.raw))
print(len(cache.decompressed))
print(cache.decode_error)

proxies = extract_proxies(cache.decompressed)
print(len(proxies))
```

### Export proxies

```python
from pathlib import Path
from src.proxy_switcher.psw_lzf import write_extracted_proxies

count = write_extracted_proxies(
    Path("tmp/proxies/proxycache_extracted_proxies.txt")
)

print(f"Exported proxies: {count}")
```

### Backup cache

```python
from src.proxy_switcher.psw_lzf import backup_lzf

backup_path = backup_lzf()
print(f"Backup saved to: {backup_path}")
```

### Replace a same-length proxy

Same-length replacement is the safest direct mutation because it does not shift record sizes.

```python
from src.proxy_switcher.psw_lzf import replace_proxy_same_length

count = replace_proxy_same_length(
    "62.60.149.161:1080",
    "62.60.149.162:1080",
)

print(f"Replaced records: {count}")
```

Both proxy strings must have the same length.

Good:

```text
62.60.149.161:1080
62.60.149.162:1080
```

Bad:

```text
62.60.149.161:1080
1.2.3.4:80
```

## Generic modification

Use `modify_lzf()` when you need custom byte-level changes.

```python
from src.proxy_switcher.psw_lzf import modify_lzf

def patch(buf: bytearray) -> None:
    old = b"62.60.149.161:1080"
    new = b"62.60.149.162:1080"

    pos = buf.find(old)
    if pos >= 1 and buf[pos - 1] == len(old):
        buf[pos:pos + len(old)] = new

modify_lzf(patch)
```

## Low-level update

Use `update_lzf()` only when you already have a complete decompressed payload.

```python
from src.proxy_switcher.psw_lzf import read_lzf, update_lzf

cache = read_lzf()
new_decompressed = cache.decompressed

update_lzf(new_decompressed)
```

## Real-cache test behavior

The test suite includes a real-cache copy test that:

1. Copies the real cache from `%APPDATA%\WNR\PSW\proxycache.lzf`
2. Writes the copy into `tmp/proxies`
3. Decodes the copied cache
4. Appends a test length-prefixed proxy record to the copied decompressed payload
5. Rewrites only the copied cache
6. Verifies the appended test proxy can be extracted

It does not modify the real Proxy Switcher database.

Run only that test:

```cmd
pytest -q tests\proxy_switcher\test_psw_lzf.py::test_real_proxycache_copy_decode_and_append_proxy
```

Run all tests:

```cmd
pytest -q tests\proxy_switcher\test_psw_lzf.py
```

## Diagnostic scripts

Several diagnostic scripts are available during development:

```text
src/proxy_switcher/inspect_proxycache.py
src/proxy_switcher/inspect_decompressed.py
src/proxy_switcher/extract_database.py
src/proxy_switcher/extract_database2.py
src/proxy_switcher/rewrite_database.py
src/proxy_switcher/replace_proxy_test.py
```

These scripts were used to discover and validate:

* real cache location
* `LZF1` header
* payload offset
* tolerant LZF decompression behavior
* decompressed proxy record layout
* roundtrip rewrite compatibility

## Known limitations

Appending a basic length-prefixed proxy record is enough for extraction tests, but it may not be enough for Proxy Switcher UI integration.

The real application may require additional fields such as:

* group/category id
* country
* anonymity level
* status
* timestamps
* scanner metadata
* record counters or indexes

For production-safe insertion, prefer one of these approaches:

1. Use Proxy Switcher's built-in import feature.
2. Clone a complete existing decompressed proxy record and modify the proxy string.
3. Avoid arbitrary raw append unless only extraction is needed.

## Important

Never rewrite the real cache while Proxy Switcher is running.

Always test with a copied cache first:

```text
tmp/proxies/test_proxycache.lzf
```

Only update the real cache after the copied file roundtrip has been verified.

```
```

[1]: https://www.proxyswitcher.com/ "Anonymous Browsing via Proxy Servers with Proxy Switcher"
