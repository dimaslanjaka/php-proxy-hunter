import setuptools

with open('readme.md', 'r') as f:
    long_description = f.read()

setuptools.setup(
    name='proxy_hunter',
    version='0.1',
    packages=['proxy_hunter'],
    install_requires=['pycurl', 'netaddr', 'colorama'],
    author='ricerati',
    description='Proxy checker in Python',
    long_description=long_description,
    long_description_content_type='text/markdown',
    keywords='proxy checker',
    project_urls={
        'Source Code': 'https://github.com/xajnx/proxyhunter'
    },
    classifiers=[
        'License :: OSI Approved :: MIT License'
    ]
)
