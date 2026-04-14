import os
import shutil
import platform
import subprocess
import time
import signal
import sys
from typing import List, Optional

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func import get_relative_path
from artisan.proxy_getter import (
    load_working_proxies_from_db,
    parse_args,
    load_proxies_from_file,
)
from artisan.proxy_socks5_checker import (
    filter_test_socks5_proxies,
    to_socks5_list,
)
from src.shared import init_db

TUN2SOCKS_BIN_ENV = os.getenv("TUN2SOCKS_BIN")
TEST_URL = "http://1.1.1.1"
TIMEOUT = 8


# -----------------------------
# Binary Finder
# -----------------------------
def is_executable(path: str) -> bool:
    return os.path.isfile(path) and os.access(path, os.X_OK)


def find_tun2socks_binary() -> Optional[str]:
    system = platform.system().lower()
    candidates: List[str] = []

    if TUN2SOCKS_BIN_ENV:
        candidates.append(TUN2SOCKS_BIN_ENV)

    if system == "windows":
        candidates += [
            "tun2socks.exe",
            "badvpn-tun2socks.exe",
            "sing-box.exe",
            "xray.exe",
        ]
    else:
        candidates += [
            "tun2socks",
            "badvpn-tun2socks",
            "sing-box",
            "xray",
        ]

    for candidate in candidates:
        if os.path.isabs(candidate) and is_executable(candidate):
            return candidate

        resolved = shutil.which(candidate)
        if resolved and is_executable(resolved):
            return resolved

    return None


# -----------------------------
# Engine Detection
# -----------------------------
def detect_engine(binary: str) -> str:
    name = os.path.basename(binary).lower()

    if "badvpn" in name or "tun2socks" in name:
        return "badvpn"
    elif "sing-box" in name:
        return "singbox"
    elif "xray" in name:
        return "xray"

    return "unknown"


# -----------------------------
# Start tun2socks per engine
# -----------------------------
def start_tun2socks(binary: str, engine: str, proxy: str, tun_name: str):
    if engine == "badvpn":
        cmd = [
            binary,
            "--tundev",
            tun_name,
            "--netif-ipaddr",
            "10.0.0.2",
            "--netif-netmask",
            "255.255.255.0",
            "--socks-server-addr",
            proxy.replace("socks5://", ""),
        ]

    elif engine == "singbox":
        # minimal inline config
        config = f"""
{{
  "inbounds": [
    {{
      "type": "tun",
      "interface_name": "{tun_name}",
      "inet4_address": "10.0.0.1/24"
    }}
  ],
  "outbounds": [
    {{
      "type": "socks",
      "server": "{proxy.split(':')[1].replace('//','')}",
      "server_port": {proxy.split(':')[-1]}
    }}
  ]
}}
"""
        with open("singbox-temp.json", "w") as f:
            f.write(config)

        cmd = [binary, "run", "-c", "singbox-temp.json"]

    elif engine == "xray":
        # simplified config
        config = f"""
{{
  "inbounds": [
    {{
      "protocol": "tun",
      "settings": {{
        "name": "{tun_name}",
        "address": ["10.0.0.1/24"]
      }}
    }}
  ],
  "outbounds": [
    {{
      "protocol": "socks",
      "settings": {{
        "servers": [
          {{
            "address": "{proxy.split(':')[1].replace('//','')}",
            "port": {proxy.split(':')[-1]}
          }}
        ]
      }}
    }}
  ]
}}
"""
        with open("xray-temp.json", "w") as f:
            f.write(config)

        cmd = [binary, "-config", "xray-temp.json"]

    else:
        raise RuntimeError("Unsupported tun2socks engine")

    return subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


# -----------------------------
# Connectivity Test
# -----------------------------
def test_connectivity(tun_name: str) -> bool:
    try:
        result = subprocess.run(
            ["curl", "--interface", tun_name, "-m", "5", TEST_URL],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return result.returncode == 0
    except:
        return False


# -----------------------------
# Stop process safely
# -----------------------------
def stop_process(proc):
    if not proc:
        return

    if proc.poll() is None:
        proc.terminate()
        try:
            proc.wait(timeout=2)
        except subprocess.TimeoutExpired:
            proc.kill()


def find_first_working_proxy(proxies: List[str]) -> Optional[str]:
    binary = find_tun2socks_binary()

    if not binary:
        raise RuntimeError("tun2socks binary not found")

    engine = detect_engine(binary)
    print(f"[INFO] Using binary: {binary} ({engine})")

    for i, proxy in enumerate(proxies):
        tun_name = f"tun{i % 3}"  # reuse small pool

        print(f"[TRY] {proxy}")

        proc = None
        try:
            proc = start_tun2socks(binary, engine, proxy, tun_name)

            time.sleep(2)  # allow init

            if test_connectivity(tun_name):
                print(f"[SUCCESS] {proxy}")
                return proxy
            else:
                print(f"[FAIL] {proxy}")

        except Exception as e:
            print(f"[ERROR] {proxy} -> {e}")

        finally:
            stop_process(proc)

    return None


# -----------------------------
# Example Usage
# -----------------------------
if __name__ == "__main__":
    args = parse_args()

    db = init_db("mysql")
    proxies = load_working_proxies_from_db(db, args.limit, True, True)
    proxies = load_proxies_from_file(get_relative_path("proxies.txt"))
    proxy_list = to_socks5_list(proxies)

    def on_success(proxy, _):
        db.update_data(proxy, {"type": "socks5", "status": "active", "https": "true"})

    proxy_list = filter_test_socks5_proxies(
        proxy_list,
        timeout=TIMEOUT,
        on_success=on_success,
        return_early_first_working=True,
    )

    result = find_first_working_proxy(proxy_list)

    print("RESULT:", result)
