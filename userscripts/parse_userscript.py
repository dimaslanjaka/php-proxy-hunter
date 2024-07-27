import os
import re
import sys
from typing import List, Set

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "../")))

from src.func import get_relative_path


def extract_domains_from_userscript(filepath: str) -> List[str]:
    """
    Extracts a list of domains from a userscript file based on @match directives.

    Args:
        filepath (str): The path to the userscript file.

    Returns:
        List[str]: A list of unique domain names extracted from the @match directives.
    """
    with open(filepath, "r") as file:
        content = file.read()

    # Print the first 1000 characters of the file content for debugging
    # print("File Content Snippet:")
    # print(content[:1000])

    # Updated regex to capture @match directives
    match_pattern = re.compile(r"@match\s+([^\s]+)")
    matches = match_pattern.findall(content)

    # print("Matches Found:")
    # for match in matches:
    #     print(f"Match: {match}")

    domains: Set[str] = set()
    for match in matches:
        # print(f"Processing Match: {match}")
        # Handle wildcard '*' by extracting base URL
        if "*" in match:
            # Simplified extraction logic for wildcard patterns
            if "://" in match:
                base_url = match.split("/")[2]  # Get the domain part
            else:
                base_url = match.split("/")[0]  # Handle cases without '://'
        else:
            domain_match = re.match(r"https?://([^/]+)", match)
            base_url = domain_match.group(1) if domain_match else None

        if base_url:
            # print(f"Extracted Base URL: {base_url}")
            domains.add(base_url)
        else:
            print(f"Invalid or Unsupported Match: {match}")

    # Convert set to list before returning
    return list(domains)


def main():
    userscript_path = get_relative_path("userscripts/universal.user.js")
    print(f"Userscript Path: {userscript_path}")
    domains = extract_domains_from_userscript(userscript_path)

    # Generate Django CORS settings
    cors_allowed_origins = ["https://" + domain for domain in domains]
    print("CORS_ALLOWED_ORIGINS = [")
    for domain in cors_allowed_origins:
        print(f"    '{domain}',")
    print("]")


if __name__ == "__main__":
    main()
