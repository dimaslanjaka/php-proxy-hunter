@echo off

"%~dp0py.cmd" -m pip install -r "%~dp0..\requirements-dev.txt"
"%~dp0py.cmd" packages/proxy-hunter-python/setup.py develop
"%~dp0py.cmd" packages/proxy-checker-python/setup.py develop
"%~dp0py.cmd" packages/rsa-utility/setup.py develop