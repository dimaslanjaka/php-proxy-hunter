import asyncio
import ssl
import time
import socket
import socks
import os
import sys
import re
from typing import Any, Awaitable, Callable
from colorama import Fore, Style, just_fix_windows_console

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.shared import init_db
from src.func import get_relative_path
from artisan.proxy_getter import (
    load_proxies_from_cli,
    load_proxies_from_file,
    normalize_proxy_str,
    parse_args,
)

TARGET_HOST = "1.1.1.1"
TLS_HOST = "www.google.com"
HTTP_TEST = "http://httpbin.org/ip"

TIMEOUT = 5
TARGET_SCORE = 70

just_fix_windows_console()
COLOR_ENABLED = True


def color_value_text(value: int) -> str:
    if not COLOR_ENABLED:
        return str(value)

    clamped = max(0, min(100, value))
    red = int(255 * (100 - clamped) / 100)
    green = int(255 * clamped / 100)
    return f"\x1b[38;2;{red};{green};0m{value}{Style.RESET_ALL}"


def color_proxy_text(value: str) -> str:
    if not COLOR_ENABLED:
        return value
    return f"{Fore.CYAN}{value}{Style.RESET_ALL}"


def color_score_value_text(message: str, stage: str) -> str:
    if stage not in {"SCORE", "WORKER"}:
        return message

    def replace_score_value(match: re.Match[str]) -> str:
        value_text = match.group("value")
        value = int(value_text)
        if value > 100:
            return match.group(0)

        prefix = match.group("prefix")
        suffix = match.group("suffix")
        return f"{prefix}{color_value_text(value)}{suffix}"

    return re.sub(
        r"(?P<prefix>\b(?:score\s+result|done|target\s+reached)\s*\()(?P<value>\d{1,3})(?P<suffix>\))",
        replace_score_value,
        message,
        flags=re.IGNORECASE,
    )


def color_status_text(message: str, stage: str) -> str:
    if not COLOR_ENABLED:
        return message

    highlighted = color_score_value_text(message, stage)

    def replacer(match: re.Match[str]) -> str:
        word = match.group(0)
        lowered = word.lower()
        if lowered.startswith("fail"):
            return f"{Fore.RED}{word}{Style.RESET_ALL}"
        return f"{Fore.GREEN}{word}{Style.RESET_ALL}"

    return re.sub(
        r"\b(pass|success|succeed|succeeded|fail|failed)\b",
        replacer,
        highlighted,
        flags=re.IGNORECASE,
    )


def log_test(proxy, stage, message):
    proxy_text = f"{proxy[0]}:{proxy[1]}"
    colored_proxy = color_proxy_text(proxy_text)
    colored_message = color_status_text(message, stage)
    print(f"[{stage}] {colored_proxy} - {colored_message}", flush=True)


def normalize_proxy_text(value: str) -> str:
    raw = value.strip()
    if raw.startswith("socks5://"):
        return raw.replace("socks5://", "", 1)
    if "://" in raw:
        return raw.split("://", 1)[1]
    return raw


def normalize_proxy_line_for_match(value: str) -> str | None:
    raw = normalize_proxy_text(value)

    parsed = normalize_proxy_str(raw)
    if parsed:
        return f"{parsed[0]}:{parsed[1]}"

    # Support source rows like host:port:HTTP by dropping trailing type suffix.
    if ":" in raw:
        parsed = normalize_proxy_str(raw.rsplit(":", 1)[0])
        if parsed:
            return f"{parsed[0]}:{parsed[1]}"

    return None


def proxy_to_host_port(proxy: Any) -> str | None:
    if isinstance(proxy, (tuple, list)) and len(proxy) >= 2:
        return f"{proxy[0]}:{proxy[1]}"

    if isinstance(proxy, str):
        return normalize_proxy_text(proxy)

    if isinstance(proxy, dict):
        proxy_value = str(proxy.get("proxy") or "").strip()
        if proxy_value:
            return normalize_proxy_text(proxy_value)
        if proxy.get("ip") and proxy.get("port"):
            return f"{proxy['ip']}:{proxy['port']}"

    return None


def proxy_to_tuple(proxy: Any) -> tuple[str, int] | None:
    if isinstance(proxy, (tuple, list)) and len(proxy) >= 2:
        try:
            return str(proxy[0]), int(proxy[1])
        except (TypeError, ValueError):
            return None

    if isinstance(proxy, str):
        return normalize_proxy_str(normalize_proxy_text(proxy))

    if isinstance(proxy, dict):
        proxy_value = str(proxy.get("proxy") or "").strip()
        if proxy_value:
            parsed = normalize_proxy_str(normalize_proxy_text(proxy_value))
            if parsed:
                return parsed
        if proxy.get("ip") and proxy.get("port"):
            return normalize_proxy_str(f"{proxy['ip']}:{proxy['port']}")

    return None


# ---------- CORE TESTS ----------


async def test_tcp(proxy):
    host, port = proxy
    log_test(proxy, "TCP", "start")
    try:
        s = socks.socksocket()
        s.set_proxy(socks.SOCKS5, host, int(port))
        s.settimeout(TIMEOUT)
        s.connect((TARGET_HOST, 443))
        s.close()
        log_test(proxy, "TCP", "pass")
        return True
    except Exception as exc:
        log_test(proxy, "TCP", f"fail ({exc})")
        return False


async def test_tls(proxy):
    host, port = proxy
    log_test(proxy, "TLS", "start")
    try:
        s = socks.socksocket()
        s.set_proxy(socks.SOCKS5, host, int(port))
        s.settimeout(TIMEOUT)
        s.connect((TLS_HOST, 443))

        ctx = ssl.create_default_context()
        ssl_sock = ctx.wrap_socket(s, server_hostname=TLS_HOST)
        ssl_sock.close()
        log_test(proxy, "TLS", "pass")
        return True
    except Exception as exc:
        log_test(proxy, "TLS", f"fail ({exc})")
        return False


async def test_stability(proxy):
    host, port = proxy
    log_test(proxy, "STABILITY", "start")
    try:
        s = socks.socksocket()
        s.set_proxy(socks.SOCKS5, host, int(port))
        s.settimeout(TIMEOUT)
        s.connect((TARGET_HOST, 443))

        log_test(proxy, "STABILITY", "holding connection for 5s")
        await asyncio.sleep(5)

        s.send(b"GET / HTTP/1.1\r\nHost: 1.1.1.1\r\n\r\n")
        s.close()
        log_test(proxy, "STABILITY", "pass")
        return True
    except Exception as exc:
        log_test(proxy, "STABILITY", f"fail ({exc})")
        return False


async def test_latency(proxy):
    host, port = proxy
    log_test(proxy, "LATENCY", "start")
    try:
        start = time.time()

        s = socks.socksocket()
        s.set_proxy(socks.SOCKS5, host, int(port))
        s.settimeout(TIMEOUT)
        s.connect((TARGET_HOST, 443))
        s.close()

        latency = time.time() - start
        log_test(proxy, "LATENCY", f"pass ({latency:.3f}s)")
        return latency
    except Exception as exc:
        log_test(proxy, "LATENCY", f"fail ({exc})")
        return None


# ---------- SCORING ----------


async def score_proxy(proxy):
    score = 0
    log_test(proxy, "SCORE", "start")

    tcp = await test_tcp(proxy)
    if not tcp:
        log_test(proxy, "SCORE", "hard fail (tcp)")
        return 0  # hard fail

    score += 20

    tls = await test_tls(proxy)
    if tls:
        score += 30

    stability = await test_stability(proxy)
    if stability:
        score += 25

    latency = await test_latency(proxy)
    if latency:
        if latency < 0.5:
            score += 10
        elif latency < 1.5:
            score += 5

    # NOTE: remote DNS test skipped (can add via aiohttp + socks5h)

    log_test(proxy, "SCORE", f"done ({score})")
    return score


# ---------- WORKER POOL ----------


async def _invoke_worker_callback(
    callback: Callable[[tuple[str, int], int], Any] | None,
    proxy_tuple: tuple[str, int],
    score: int,
):
    if callback is None:
        return

    try:
        callback_result = callback(proxy_tuple, score)
        if asyncio.iscoroutine(callback_result):
            await callback_result
    except Exception as exc:
        log_test(proxy_tuple, "CALLBACK", f"fail ({exc})")


async def worker(
    queue,
    found_event,
    result_holder,
    tested_set,
    on_success: Callable[[tuple[str, int], int], Any] | None = None,
    on_failure: Callable[[tuple[str, int], int], Any] | None = None,
):
    while not found_event.is_set():
        try:
            proxy = await asyncio.wait_for(queue.get(), timeout=1)
        except asyncio.TimeoutError:
            return

        try:
            proxy_tuple = proxy_to_tuple(proxy)
            if not proxy_tuple:
                continue

            tested_set.add(f"{proxy_tuple[0]}:{proxy_tuple[1]}")
            log_test(proxy_tuple, "WORKER", "picked from queue")
            score = await score_proxy(proxy_tuple)
            log_test(proxy_tuple, "WORKER", f"score result ({score})")

            if score >= TARGET_SCORE:
                await _invoke_worker_callback(on_success, proxy_tuple, score)
                result_holder.append((proxy_tuple, score))
                log_test(proxy_tuple, "WORKER", f"target reached ({TARGET_SCORE})")
                found_event.set()  # 🚀 STOP EVERYTHING
                return

            await _invoke_worker_callback(on_failure, proxy_tuple, score)

        finally:
            queue.task_done()


async def run(proxies, concurrency=200):
    queue = asyncio.Queue()
    found_event = asyncio.Event()
    results = []
    tested_set = set()

    for p in proxies:
        await queue.put(p)

    tasks = [
        asyncio.create_task(worker(queue, found_event, results, tested_set))
        for _ in range(concurrency)
    ]

    await queue.join()

    for t in tasks:
        t.cancel()

    return sorted(results, key=lambda x: x[1], reverse=True), tested_set


async def run_until_found(
    proxies,
    concurrency=200,
    on_success: Callable[[tuple[str, int], int], Any] | None = None,
    on_failure: Callable[[tuple[str, int], int], Any] | None = None,
):
    queue = asyncio.Queue()
    found_event = asyncio.Event()
    result_holder = []
    tested_set = set()

    for p in proxies:
        await queue.put(p)

    tasks = [
        asyncio.create_task(
            worker(
                queue,
                found_event,
                result_holder,
                tested_set,
                on_success=on_success,
                on_failure=on_failure,
            )
        )
        for _ in range(concurrency)
    ]

    # wait until found OR queue exhausted
    queue_done_task = asyncio.create_task(queue.join())
    found_task = asyncio.create_task(found_event.wait())
    done, pending = await asyncio.wait(
        [queue_done_task, found_task],
        return_when=asyncio.FIRST_COMPLETED,
    )

    # Two-phase shutdown is intentional:
    # 1) cancel every pending task first, so all receive cancellation promptly
    # 2) await each task after cancellation to let the loop process cleanup
    # Keeping these phases separate avoids serial cancel/await behavior.
    for pending_task in pending:
        pending_task.cancel()
    for pending_task in pending:
        try:
            await pending_task
        except asyncio.CancelledError:
            pass

    # cancel all remaining workers
    for t in tasks:
        t.cancel()

    # wait cancellation properly
    task_results = await asyncio.gather(*tasks, return_exceptions=True)
    for task_result in task_results:
        if isinstance(task_result, Exception) and not isinstance(
            task_result, asyncio.CancelledError
        ):
            raise task_result

    return result_holder[0] if result_holder else None, tested_set


# ---------- ENTRY ----------

if __name__ == "__main__":
    args = parse_args()
    proxy_file = get_relative_path("proxies.txt")

    proxies = []
    source_label = "DB"

    cli_proxies = load_proxies_from_cli()
    if len(cli_proxies) != 0:
        proxies = cli_proxies
        source_label = "CLI input"
        print(f"{source_label} {len(proxies)} proxies loaded")

    if len(proxies) == 0:
        proxies = load_proxies_from_file(proxy_file)
        if proxies:
            source_label = f"file input ({proxy_file})"
            print(f"{source_label} {len(proxies)} proxies loaded")

    if len(proxies) == 0:
        source_label = "DB"
        db = init_db("mysql")
        try:
            proxies = db.get_working_proxies(
                auto_fix=False,
                randomize=True,
                limit=args.limit,
                proxy_type="socks5",
                ssl=None,
                tun2socks=False,
            )
            print(f"{source_label} {len(proxies)} proxies loaded")
        finally:
            db.close()

    if not proxies:
        print(f"No working SOCKS5 proxies found from {source_label}")
        sys.exit(0)

    db = init_db("mysql")
    db_write_lock = asyncio.Lock()

    async def on_success(proxy_tuple: tuple[str, int], score: int):
        proxy = f"{proxy_tuple[0]}:{proxy_tuple[1]}"
        async with db_write_lock:
            db.update_data(
                proxy,
                {"tun2socks": score, "type": "socks5", "status": "active"},
            )

    async def on_failure(proxy_tuple: tuple[str, int], score: int):
        proxy = f"{proxy_tuple[0]}:{proxy_tuple[1]}"
        async with db_write_lock:
            db.update_data(proxy, {"tun2socks": 0})

    try:
        result, tested_set = asyncio.run(
            run_until_found(
                proxies,
                args.concurrency,
                on_success=on_success,
                on_failure=on_failure,
            )
        )
    finally:
        db.close()

    if "file" in source_label and tested_set and os.path.isfile(proxy_file):
        with open(proxy_file, "r", encoding="utf-8") as f:
            file_lines = f.readlines()

        kept_lines = []
        removed_count = 0
        for line in file_lines:
            normalized = normalize_proxy_line_for_match(line)
            if normalized in tested_set:
                removed_count += 1
                continue
            kept_lines.append(line)

        trimmed_trailing_empty = 0
        while kept_lines and not kept_lines[-1].strip():
            kept_lines.pop()
            trimmed_trailing_empty += 1

        if removed_count or trimmed_trailing_empty:
            with open(proxy_file, "w", encoding="utf-8") as f:
                f.writelines(kept_lines)
            print(
                f"[INFO] Removed {removed_count} tested proxies from {proxy_file}; "
                f"trimmed {trimmed_trailing_empty} trailing empty lines"
            )

    if result:
        proxy_tuple, score = result
        print(f"FOUND: {proxy_tuple} => {score}")
    else:
        print("No compatible tun2socks proxy found")
