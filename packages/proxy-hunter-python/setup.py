import setuptools

with open("readme.md", "r") as f:
    long_description = f.read()

setuptools.setup(
    name="proxy_hunter",
    version="1.0",
    packages=[
        "proxy_hunter",
        "proxy_hunter.curl",
        "proxy_hunter.utils",
        "proxy_hunter.scrappers",
    ],
    install_requires=[
        "pycurl",
        "netaddr",
        "colorama",
        "chardet",
        "requests",
        "brotli",
        "ipaddress",
        "pytest",
        "urlextract",
        "filelock",
        "asyncio",
        "brotli",
        "chardet",
        "proxy-checker",
        "beautifulsoup4",
        "httpx",
        "pySocks",
    ],
    test_suite="tests",
    author="Dimas Lanjaka",
    author_email="dimaslanjaka@gmail.com",
    description="Proxy hunter utility in Python",
    long_description=long_description,
    long_description_content_type="text/markdown",
    keywords="proxy checker",
    project_urls={
        "Source Code": "https://github.com/dimaslanjaka/php-proxy-hunter/packages/proxy-hunter-python"
    },
    classifiers=["License :: OSI Approved :: MIT License"],
)
