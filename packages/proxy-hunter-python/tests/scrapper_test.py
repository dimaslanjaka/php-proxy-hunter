import argparse

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

    scrape(args.proxy, args.output, args.verbose)


def main():
    scrape("http", "tmp/output.txt", True)


if __name__ == "__main__":
    main()
