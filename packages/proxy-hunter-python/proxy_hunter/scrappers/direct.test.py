import argparse
import asyncio
import platform
import sys

from proxy_hunter.scrappers.scrapper import scrape, scrapers


def modular():
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "-p",
        "--proxy",
        help="Supported proxy type: "
        + ", ".join(sorted(set([s.method for s in scrapers]))),
        required=True,
    )
    parser.add_argument(
        "-o",
        "--output",
        help="Output file name to save .txt file",
        default="tmp/output.txt",
    )
    parser.add_argument(
        "-v",
        "--verbose",
        help="Increase output verbosity",
        action="store_true",
    )
    args = parser.parse_args()

    coro = scrape(args.proxy, args.output, args.verbose)

    try:
        asyncio.run(coro)
    except RuntimeError:
        # Fallback for Windows or if already inside a running event loop
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        loop.run_until_complete(coro)
        loop.close()


def main():
    loop = asyncio.get_event_loop()
    loop.run_until_complete(scrape("http", "tmp/output.txt", True))


if __name__ == "__main__":
    main()
