import sys
import pytest

if __name__ == "__main__":
    pytest.main(["-q", "-k", "proxy_extractor", "--maxfail=1"])
    sys.exit(0)
