import setuptools

with open("readme.md", "r") as f:
    long_description = f.read()

setuptools.setup(
    name="rsa_utility",
    version="0.1",
    packages=["rsa_utility"],
    install_requires=["pycurl", "netaddr", "colorama", "chardet", "requests", "brotli"],
    author="ricerati",
    description="Proxy checker in Python",
    long_description=long_description,
    long_description_content_type="text/markdown",
    keywords="proxy checker",
    project_urls={"Source Code": "https://github.com/xajnx/proxyhunter"},
    classifiers=["License :: OSI Approved :: MIT License"],
)
