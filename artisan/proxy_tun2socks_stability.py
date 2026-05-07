import asyncio
import ssl
import time
import socks
import os
import sys
import re
from typing import Any, Callable, Optional, TypedDict, List

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from src.func_console import cyan, green, red
from src.shared import init_db
from src.func import get_relative_path
from src.utils.file.FileLockHelper import FileLockHelper
from src.utils.file import remove_string_from_file
from artisan.proxy_getter import (
    normalize_proxy_str,
    retrieve_proxies,
    ProxyRetrievalResult,
)
from src.func_date import is_date_rfc3339_older_than
from src.utils.parse_args import parse_args
from src.geoPlugin import get_geo_ip

TARGET_HOST = "1.1.1.1"
TLS_HOST = "www.google.com"
HTTP_TEST = "http://httpbin.org/ip"

TIMEOUT = 5
TARGET_SCORE = 70

current_filename = os.path.basename(__file__)
locker: Optional[FileLockHelper] = None


class ProxyScoreResult(TypedDict):
    score: int
    tcp: bool
    tls: bool
    stability: bool
    latency: float | None


def color_value_text(value: int) -> str:
    clamped = max(0, min(100, value))
    red = int(255 * (100 - clamped) / 100)
    green = int(255 * clamped / 100)
    reset = "\x1b[0m"
    return f"\x1b[38;2;{red};{green};0m{value}{reset}"


def color_proxy_text(value: str) -> str:
    return cyan(value)


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
    highlighted = color_score_value_text(message, stage)

    def replacer(match: re.Match[str]) -> str:
        word = match.group(0)
        lowered = word.lower()
        if lowered.startswith("fail"):
            return red(word)
        return green(word)

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


def proxy_to_marker_key(proxy: Any) -> str | None:
    parsed = proxy_to_tuple(proxy)
    if parsed:
        return f"{parsed[0]}:{parsed[1]}"

    fallback = proxy_to_host_port(proxy)
    if fallback:
        return fallback.strip()

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


async def score_proxy(proxy) -> ProxyScoreResult:
    score = 0
    log_test(proxy, "SCORE", "start")

    tcp = await test_tcp(proxy)
    if not tcp:
        log_test(proxy, "SCORE", "hard fail (tcp)")
        return {
            "score": 0,
            "tcp": False,
            "tls": False,
            "stability": False,
            "latency": None,
        }

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
    return {
        "score": score,
        "tcp": True,
        "tls": tls,
        "stability": stability,
        "latency": latency,
    }


# ---------- WORKER POOL ----------


async def _invoke_worker_callback(
    callback: Callable[[tuple[str, int], ProxyScoreResult], Any] | None,
    proxy_tuple: tuple[str, int],
    score_result: ProxyScoreResult,
):
    if callback is None:
        return

    try:
        callback_result = callback(proxy_tuple, score_result)
        if asyncio.iscoroutine(callback_result):
            await callback_result
    except Exception as exc:
        log_test(proxy_tuple, "CALLBACK", f"fail ({exc})")


async def worker(
    queue,
    found_event,
    result_holder,
    tested_set,
    on_success: Callable[[tuple[str, int], ProxyScoreResult], Any] | None = None,
    on_failure: Callable[[tuple[str, int], ProxyScoreResult], Any] | None = None,
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
            score_result = await score_proxy(proxy_tuple)
            score = score_result["score"]
            log_test(proxy_tuple, "WORKER", f"score result ({score})")

            if score >= TARGET_SCORE:
                await _invoke_worker_callback(on_success, proxy_tuple, score_result)
                result_holder.append((proxy_tuple, score))
                log_test(proxy_tuple, "WORKER", f"target reached ({TARGET_SCORE})")
                found_event.set()  # 🚀 STOP EVERYTHING
                return

            await _invoke_worker_callback(on_failure, proxy_tuple, score_result)

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
    on_success: Callable[[tuple[str, int], ProxyScoreResult], Any] | None = None,
    on_failure: Callable[[tuple[str, int], ProxyScoreResult], Any] | None = None,
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
    # Use shared parser which already exposes --uid
    args = parse_args()
    lock_name = args.uid if getattr(args, "uid", None) else current_filename

    # Create and acquire file lock (allow override via --fileLock)
    file_lock_arg = getattr(args, "file_lock", None)
    if file_lock_arg:
        locker = FileLockHelper(file_lock_arg)
    else:
        locker = FileLockHelper(get_relative_path(f"tmp/locks/{lock_name}.lock"))
    if not locker.lock():
        print("Another instance is running. Exiting.")
        sys.exit(0)

    try:
        proxy_file = get_relative_path("proxies.txt")

        try:
            # Retrieve proxies via central retriever (DB/file/CLI handled there)
            def custom_filter(rows: List[dict[str, Any]]) -> List[dict[str, Any]]:
                # Include rows that either have no last_check (e.g. loaded from file)
                # or whose last_check is older than the configured threshold.
                filtered = [
                    r
                    for r in rows
                    if isinstance(r, dict)
                    and (
                        not r.get("last_check")
                        or is_date_rfc3339_older_than(r.get("last_check"), hours=24)
                    )
                ]
                return filtered

            db_local = init_db("mysql")
            try:
                result = retrieve_proxies(
                    db=db_local, limit=args.limit, custom_filter=custom_filter
                )
                proxies = result.proxies
                source_label = result.source_label
                source_file = getattr(result, "source_file", None)
                print(f"{source_label} {len(proxies)} proxies loaded")

                # If proxies were loaded from a file (source_label like "file://..."),
                # prefer removing tested entries from that original file instead of
                # always using the default proxies.txt path. Prefer the explicit
                # `source_file` when available.
                candidate = None
                if source_file:
                    candidate = source_file
                elif "file" in source_label:
                    candidate = source_label.split("file://", 1)[1]
                    # Windows paths may be like file:///C:/... or file://C:/... — strip
                    # leading slashes to get a usable filesystem path.
                    candidate = candidate.lstrip("/")

                if candidate and os.path.isfile(candidate):
                    proxy_file = candidate
            finally:
                db_local.close()

            if not proxies:
                print(f"No working SOCKS5 proxies found from {source_label}")
                sys.exit(0)

            proxy_by_key: dict[str, Any] = {}
            ordered_keys: list[str] = []
            for proxy in proxies:
                marker_key = proxy_to_marker_key(proxy)
                if not marker_key or marker_key in proxy_by_key:
                    continue
                proxy_by_key[marker_key] = proxy
                ordered_keys.append(marker_key)

            pending_keys = ordered_keys
            proxies = [proxy_by_key[key] for key in pending_keys]
            already_checked = 0
            print(
                f"[MARKER] pending={len(proxies)}, already_checked={already_checked}, "
                f"total_unique={len(ordered_keys)}"
            )

            if not proxies:
                print("No untested proxies left for tun2socks stability check")
                sys.exit(0)

            db = init_db("mysql")
            db_write_lock = asyncio.Lock()

            async def on_success(
                proxy_tuple: tuple[str, int], score_result: ProxyScoreResult
            ):
                score = score_result["score"]
                tls_ok = score_result["tls"]
                # convert latency (seconds) -> milliseconds for DB
                latency = score_result.get("latency")
                latency_ms = int(latency * 1000) if latency is not None else None
                proxy = f"{proxy_tuple[0]}:{proxy_tuple[1]}"
                # retrieve geo info in thread to avoid blocking event loop
                geo = await asyncio.to_thread(get_geo_ip, proxy)
                async with db_write_lock:
                    if score > 0:
                        data = {
                            "tun2socks": score,
                            "type": "socks5",
                            "status": "active",
                            "https": "true" if tls_ok else "false",
                            "latency": latency_ms,
                        }
                        if geo:
                            if getattr(geo, "city", None):
                                data["city"] = geo.city
                            if getattr(geo, "country_name", None):
                                data["country"] = geo.country_name

                        db.update_data(proxy, data)

            async def on_failure(
                proxy_tuple: tuple[str, int], score_result: ProxyScoreResult
            ):
                score = score_result["score"]
                tls_ok = score_result["tls"]
                proxy = f"{proxy_tuple[0]}:{proxy_tuple[1]}"
                # retrieve geo info in thread to avoid blocking event loop
                geo = await asyncio.to_thread(get_geo_ip, proxy)
                async with db_write_lock:
                    latency = score_result.get("latency")
                    latency_ms = int(latency * 1000) if latency is not None else None
                    data = {
                        "tun2socks": score,
                        "https": "true" if tls_ok else "false",
                        "latency": latency_ms,
                    }
                    if geo:
                        if getattr(geo, "city", None):
                            data["city"] = geo.city
                        if getattr(geo, "country_name", None):
                            data["country"] = geo.country_name

                    db.update_data(proxy, data)

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

            # Marker functionality removed; no persistent marking performed

            # Prefer using explicit source_file when available; otherwise fall
            # back to checking `source_label` and the resolved `proxy_file`.
            source_file = getattr(result, "source_file", None)
            candidate = None
            if source_file:
                candidate = source_file
            elif "file" in source_label:
                candidate = source_label.split("file://", 1)[1].lstrip("/")

            if candidate and tested_set:
                target_file = candidate if os.path.isfile(candidate) else proxy_file

                if os.path.isfile(target_file):
                    # Remove occurrences using helper once for the full set
                    try:
                        remove_string_from_file(target_file, tested_set)
                    except Exception as e:
                        print(f"[WARN] Failed removing keys from file: {e}")

                    print(
                        f"[INFO] Attempted removal of tested proxies from {target_file}"
                    )

            if result:
                proxy_tuple, score = result
                print(f"FOUND: {proxy_tuple} => {color_value_text(score)}")
            else:
                print("No compatible tun2socks proxy found")
        finally:
            pass
    finally:
        if locker:
            locker.unlock()
