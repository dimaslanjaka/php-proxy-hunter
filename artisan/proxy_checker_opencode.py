import os
import sys
import asyncio
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional, Set
import certifi
import json

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.append(PROJECT_ROOT)

from proxy_hunter import (
    ProxyCheckResult,
    clash_verge_generate_yaml,
    clash_verge_parse_proxy_endpoint,
    clash_verge_unique_proxy_name,
)
from proxy_hunter.curl import last_merged_certificates_path
from proxy_hunter.curl.httpx_asyncio import (
    ProxyCheckHTTPXResult,
    check_http_proxy,
    check_socks5_proxy,
)
from src.database.SQLiteMarker import SQLiteMarker
from src.func import get_relative_path
from src.func_console import cyan, red, magenta, green
from src.utils.parse_args import ParseArgs, parse_args
from src.func_date import (
    get_current_rfc3339_time,
    is_date_rfc3339_older_than,
)
from src.shared import init_db
from artisan.proxy_getter import normalize_proxy_value

# Concurrency level for checking proxies
CONCURRENT = 4
# Number of working proxies to find before stopping early (to save time when just a few are needed)
STOP_AFTER = 1
# How many hours to skip a dead proxy for (SQLiteMarker expiry)
DEAD_MARK_HOURS = 6

_PROTOCOL_CHECKERS = {
    "http": check_http_proxy,
    "socks5": check_socks5_proxy,
}


def _to_proxy_check_result(
    r: ProxyCheckHTTPXResult, accept_status_codes: List[int]
) -> ProxyCheckResult:
    """Map a ProxyCheckHTTPXResult to a ProxyCheckResult."""
    return ProxyCheckResult(
        result=(
            r.status_code in accept_status_codes if r.status_code is not None else False
        ),
        latency=r.latency or 0,
        error=r.error,
        status=r.status_code,
        private=False,
        proxy=r.proxy,
        type=r.protocol,
        url=None,
    )


async def check_proxy(
    proxy: Optional[str], timeout: int, url: str, accept_status_codes: List[int]
) -> List[ProxyCheckResult]:
    """
    Check a proxy across http and socks5 protocols.

    Args:
        proxy: The proxy IP:port or URL to check.
        timeout: Request timeout in seconds.
        url: The test URL.
        accept_status_codes: Status codes considered success.

    Returns:
        List of ProxyCheckResult, one per protocol.
    """
    if not proxy:
        return []
    bare = proxy.split("://", 1)[-1] if "://" in proxy else proxy
    # verify = str(last_merged_certificates_path) or certifi.where()
    verify = certifi.where()

    tasks = {
        proto: asyncio.create_task(checker(bare, url, timeout=timeout, verify=verify))
        for proto, checker in _PROTOCOL_CHECKERS.items()
    }
    raw = [await tasks[proto] for proto in _PROTOCOL_CHECKERS]
    return [_to_proxy_check_result(r, accept_status_codes) for r in raw]


class ConcurrentLogger:
    """Log handler that keeps dead-proxy status on one line (\r overwrite)
    and breaks out working-proxy results on their own line."""

    def __init__(self):
        self._lock = asyncio.Lock()

    async def dead(self, proxy: Optional[str], details: Optional[str]):
        if proxy is None or details is None:
            return
        async with self._lock:
            sys.stdout.write(f"\r[{red('DEAD')}] {cyan(proxy)} ({details})   \r")
            sys.stdout.flush()

    async def working(
        self, proxy: Optional[str], protos: Optional[str], latencyAvg: Optional[float]
    ):
        if proxy is None or protos is None or latencyAvg is None:
            return
        async with self._lock:
            sys.stdout.write(
                f"\n[{green('WORKING')} {green(protos)}] {cyan(proxy)} (avg={magenta(f'{latencyAvg:.0f}ms')})\n"
            )
            sys.stdout.flush()


class SharedState:
    """Shared state for coordinating early abort across workers."""

    def __init__(self, target: int = STOP_AFTER):
        self.target = target
        self.stop_event = asyncio.Event()
        self.working_count = 0

    def report_working(self) -> bool:
        """Increment working count; returns True if target reached."""
        self.working_count += 1
        if self.working_count >= self.target:
            self.stop_event.set()
            return True
        return False


async def bound_check(
    sem: asyncio.Semaphore,
    state: SharedState,
    logger: ConcurrentLogger,
    marker: SQLiteMarker,
    db: Any,
    proxy: Optional[str],
    **kwargs,
) -> Optional[List[ProxyCheckResult]]:
    async with sem:
        if state.stop_event.is_set():
            return None
        results = await check_proxy(proxy, **kwargs)
        if results is None:
            return None
        working = [r for r in results if r.result]
        if working:
            state.report_working()
            protos = "-".join(r.type for r in working if r.type)
            avg = sum(r.latency for r in working) / len(working)
            await logger.working(results[0].proxy, protos, avg)
            # Persist working proxy data to the database
            p = results[0].proxy
            if p and db:
                protos_db = "/".join(r.type for r in working if r.type)
                db.update_data(
                    p,
                    {
                        "status": "active",
                        "https": "true",
                        "type": protos_db,
                        "last_check": get_current_rfc3339_time(),
                    },
                )
        else:
            details = []
            has_cert_error = False
            for r in results:
                err = r.error or ""
                if r.status is not None:
                    err = f"status={r.status}" + (f" {err}" if err else "")
                details.append(f"{r.type}:{err}" if err else r.type)
                if (
                    "CERTIFICATE_VERIFY_FAILED" in err
                    and "unable to get local issuer certificate" in err
                ):
                    has_cert_error = True
            await logger.dead(results[0].proxy, "; ".join(details))
            # Mark dead proxy for 1 hour so it is skipped on subsequent runs
            # Skip marking when failure is from a local SSL cert issue
            # (the proxy itself may be fine)
            if not has_cert_error:
                p = results[0].proxy
                if p:
                    deadline = (
                        datetime.now(timezone.utc) + timedelta(hours=DEAD_MARK_HOURS)
                    ).isoformat()
                    marker.mark(p, valid_until=deadline)
        return results


async def main():
    db = init_db()
    proxies = db.get_working_proxies()
    proxies.extend(db.get_untested_proxies())

    # Load known working proxies and check for expiration, adding expired ones to the queue
    working_proxies_file = get_relative_path("tmp/proxies/ai-working-proxies.json")
    if os.path.exists(working_proxies_file):
        try:
            with open(working_proxies_file) as f:
                data = json.load(f)
                if isinstance(data, list):
                    existing_proxies = {
                        str(p.get("proxy", "")).strip()
                        for p in proxies
                        if p.get("proxy")
                    }
                    for item in data:
                        proxy_str = None
                        last_check = None
                        if isinstance(item, dict):
                            proxy_str = item.get("proxy")
                            last_check = item.get("last_check")
                        elif isinstance(item, str):
                            proxy_str = item

                        if proxy_str:
                            proxy_str = proxy_str.strip()
                            if proxy_str not in existing_proxies:
                                if not last_check:
                                    db_rows = db.select(proxy_str)
                                    if db_rows:
                                        last_check = db_rows[0].get("last_check")

                                if not last_check or is_date_rfc3339_older_than(
                                    last_check, DEAD_MARK_HOURS
                                ):
                                    db_rows = db.select(proxy_str)
                                    if db_rows:
                                        proxies.append(db_rows[0])
                                    else:
                                        proxies.append(
                                            {"proxy": proxy_str, "status": "untested"}
                                        )
                                    existing_proxies.add(proxy_str)
        except Exception as e:
            print(f"Error checking expiration for known working proxies: {e}")

    # Initialize marker to skip recently-dead proxies
    marker = SQLiteMarker(
        db_filename="proxy_checker_ai.sqlite",
        table_name="checked_proxies",
        key_column="proxy",
        base_dir="tmp/database",
    )
    try:
        # Filter out proxies that were recently marked as dead
        proxy_values = [
            str(p.get("proxy", "")).strip() for p in proxies if p.get("proxy")
        ]
        unseen = marker.filter_unseen(proxy_values)
        proxies = [
            p for p in proxies if str(p.get("proxy", "")).strip() in unseen.pending
        ]

        # Load known working proxies from persistent file
        known_working: Set[str] = set()
        if os.path.exists(working_proxies_file):
            try:
                with open(working_proxies_file) as f:
                    data = json.load(f)
                    if isinstance(data, list):
                        for item in data:
                            if isinstance(item, dict) and item.get("proxy"):
                                known_working.add(str(item.get("proxy")).strip())
                            elif isinstance(item, str):
                                known_working.add(item.strip())
            except (json.JSONDecodeError, OSError):
                pass
        if known_working:
            print(f"Loaded {len(known_working)} known working proxies from file.")

        total = len(proxies)
        if unseen.already_checked:
            print(
                f"Skipped {unseen.already_checked} recently-dead proxies"
                f" (marked within {DEAD_MARK_HOURS} hours)"
            )
        print(
            f"Checking {total} proxies (concurrency={CONCURRENT}, stop_after={STOP_AFTER})..."
        )
        sem = asyncio.Semaphore(CONCURRENT)
        state = SharedState()
        logger = ConcurrentLogger()

        pending: set = set()
        working_by_proxy: Dict[str, List[ProxyCheckResult]] = {}
        checked = 0

        def process_results(done: set):
            nonlocal checked
            for task in done:
                try:
                    results = task.result()
                except (asyncio.CancelledError, Exception):
                    continue
                if results is None:
                    continue
                for r in results:
                    if r is not None and r.result and isinstance(r.proxy, str):
                        working_by_proxy.setdefault(r.proxy, []).append(r)

        iterator = iter(proxies)
        while True:
            # Fill the pending set up to CONCURRENT * 2 or exhaust iterator
            while not state.stop_event.is_set() and len(pending) < CONCURRENT * 2:
                try:
                    data = next(iterator)
                    checked += 1
                    task = asyncio.create_task(
                        bound_check(
                            sem,
                            state,
                            logger,
                            marker,
                            db,
                            proxy=data.get("proxy"),
                            timeout=10,
                            url="https://opencode.ai/",
                            accept_status_codes=[
                                200,
                                301,
                                302,
                                307,
                                308,
                                403,
                                401,
                                403,
                                404,
                            ],
                        )
                    )
                    pending.add(task)
                except StopIteration:
                    break

            if not pending:
                break

            # Wait for at least one to finish
            done, pending = await asyncio.wait(
                pending, return_when=asyncio.FIRST_COMPLETED
            )
            process_results(done)

            # Early abort if target reached
            if state.stop_event.is_set():
                for t in pending:
                    t.cancel()
                if pending:
                    await asyncio.wait(pending)
                break

        stopped_early = state.stop_event.is_set()
        if stopped_early:
            print(
                f"\nStopped early after {state.working_count} working proxies found (checked {checked}/{total})."
            )
        print(f"\n{len(working_by_proxy)} proxies are working:")
        for proxy, ok_list in working_by_proxy.items():
            protos = ",".join(r.type for r in ok_list if r.type)
            avg = sum(r.latency for r in ok_list if r.latency >= 0) / max(
                len([r for r in ok_list if r.latency >= 0]), 1
            )
            latencies = ",".join(
                f"{(r.type or 'unknown')}={r.latency:.0f}ms" for r in ok_list
            )
            print(f"  {proxy} [{protos}] (avg={avg:.0f}ms, {latencies})")

        # Merge with known working proxies and persist
        all_working = known_working | set(working_by_proxy.keys())
        os.makedirs(os.path.dirname(working_proxies_file), exist_ok=True)
        with open(working_proxies_file, "w") as f:
            json.dump(sorted(all_working), f, indent=2)
        print(f"Saved {len(all_working)} working proxies to {working_proxies_file}")

        # Generate Clash Verge config from saved working proxies
        clash_output_file = get_relative_path("tmp/proxies/opencode-clash-verge.yaml")
        clash_proxies = []
        for proxy in sorted(all_working):
            ok_list = working_by_proxy.get(proxy)
            if ok_list:
                types = [r.type for r in ok_list if r.type]
                preferred = next(
                    (t for t in types if t in {"http", "socks5"}),
                    types[0] if types else "http",
                )
            else:
                preferred = "http"
            clash_proxies.append(
                {
                    "proxy": proxy,
                    "type": preferred,
                    "status": "active",
                    "https": "true",
                }
            )
        clash_result = clash_verge_generate_yaml(
            working_proxies=clash_proxies,
            output_file=clash_output_file,
        )
        print(
            f"Saved Clash Verge config to {cyan(clash_output_file)} "
            f"with {clash_result['proxy_count']} proxies"
        )
        if clash_result["skipped_count"]:
            print(
                f"Skipped {clash_result['skipped_count']} proxies "
                f"because they are unsupported or invalid for Clash config"
            )
    finally:
        marker.close()


if __name__ == "__main__":
    asyncio.run(main())
