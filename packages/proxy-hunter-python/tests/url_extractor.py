from proxy_hunter import extract_url

string = """
Text with URLs. Let's have URL janlipovsky.cz as an example.

URL Extractor - Chrome Web Store
Chrome Web Store
https://chromewebstore.google.com › url-extractor › gg...
30 May 2024 — Use URL Extractor - The ultimate url scanner and link grabber for detecting and exporting links effortlessly from any webpage.

Online URL Extractor To Extract URLs From Text
MiniWebtool
https://miniwebtool.com › url-extractor
This tool will extract all URLs from text. It works with all standard links, including those with non-English characters, if the link includes a trailing slash ...
"""

print(extract_url(string))
