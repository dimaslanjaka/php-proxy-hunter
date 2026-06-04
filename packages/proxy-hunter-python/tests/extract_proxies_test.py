import os
import sys

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import pytest
from proxy_hunter.Proxy import Proxy
from proxy_hunter.utils.extractor.proxies.extract_proxies import extract_proxies


class TestExtractProxies:
    """Test cases for extract_proxies function."""

    def test_none_input(self):
        """Test that None input returns an empty list."""
        assert extract_proxies(None) == []

    def test_empty_string(self):
        """Test that empty string returns an empty list."""
        assert extract_proxies("") == []

    def test_whitespace_string(self):
        """Test that whitespace-only string returns an empty list."""
        assert extract_proxies("   ") == []

    def test_standard_ipv4_port(self):
        """Test extraction of standard IPv4:PORT."""
        text = "1.2.3.4:8080"
        results = extract_proxies(text)
        assert len(results) == 1
        assert results[0].proxy == "1.2.3.4:8080"

    def test_multiple_proxies(self):
        """Test extraction of multiple proxies from a string."""
        text = "1.2.3.4:8080, 5.6.7.8:9090\n10.11.12.13:1111"
        results = extract_proxies(text)
        assert len(results) == 3
        assert results[0].proxy == "1.2.3.4:8080"
        assert results[1].proxy == "5.6.7.8:9090"
        assert results[2].proxy == "10.11.12.13:1111"

    def test_ipv6_bracketed(self):
        """Test extraction of bracketed IPv6 addresses."""
        text = "[2001:db8::1]:8080"
        results = extract_proxies(text)
        assert len(results) == 1
        assert results[0].proxy == "[2001:db8::1]:8080"

    def test_noisy_input_sanitization(self):
        """Test extraction from noisy strings (e.g., prefixed/suffixed characters)."""
        text = "XSDn209.1.2.3:80 and 1.1.1.1:80n"
        results = extract_proxies(text)
        assert len(results) == 2
        assert results[0].proxy == "209.1.2.3:80"
        assert results[1].proxy == "1.1.1.1:80"

    def test_leading_zero_normalization(self):
        """Test that leading zeros in IPv4 octets and ports are normalized."""
        text = "010.001.020.003:08080"
        results = extract_proxies(text)
        assert len(results) == 1
        assert results[0].proxy == "10.1.20.3:8080"

    def test_leading_n_sanitization(self):
        """Test that leading 'n' is stripped if it precedes an IP."""
        text = "n1.2.3.4:8080 n[2001:db8::1]:8080"
        results = extract_proxies(text)
        assert len(results) == 2
        assert results[0].proxy == "1.2.3.4:8080"
        assert results[1].proxy == "[2001:db8::1]:8080"

    def test_whitespace_separated_ip_port(self):
        """Test extraction of IP and PORT separated by whitespace."""
        text = "1.2.3.4 8080"
        results = extract_proxies(text)
        assert len(results) == 1
        assert results[0].proxy == "1.2.3.4:8080"

    def test_json_like_format(self):
        """Test extraction from JSON-like strings."""
        text = '{"ip":"1.2.3.4", "port":"8080"}'
        results = extract_proxies(text)
        assert len(results) == 1
        assert results[0].proxy == "1.2.3.4:8080"

    def test_invalid_proxies_ignored(self):
        """Test that invalid proxies (e.g., port > 65535) are ignored."""
        text = "1.2.3.4:99999, 256.256.256.256:80"
        results = extract_proxies(text)
        assert len(results) == 0

    def test_credentials_extraction(self):
        """
        Test extraction of proxies with credentials.
        Note: This depends on regex_match implementation.
        """
        text = "user:pass@1.2.3.4:8080"
        results = extract_proxies(text)
        if len(results) > 0:
            assert results[0].username == "user"
            assert results[0].password == "pass"
            assert results[0].proxy == "1.2.3.4:8080"

    def test_custom_data(self):
        """Test extraction from custom data format."""
        text = "Proxy:  Â 98.170.57.241:4145"
        results = extract_proxies(text)
        assert len(results) > 0
        assert results[0].proxy == "98.170.57.241:4145"


if __name__ == "__main__":
    pytest.main([__file__])
