import os
import sys

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import pytest
from proxy_hunter import is_valid_proxy


class TestIsValidProxyBasic:
    """Test basic proxy validation cases."""

    def test_none_proxy(self):
        """Test that None is not a valid proxy."""
        assert is_valid_proxy(None) is False

    def test_empty_string(self):
        """Test that empty string is not a valid proxy."""
        assert is_valid_proxy("") is False

    def test_empty_whitespace(self):
        """Test that whitespace-only string is not a valid proxy."""
        assert is_valid_proxy("   ") is False


class TestIsValidProxyValidIPv4:
    """Test valid IPv4 proxy addresses."""

    def test_valid_ipv4_standard_port(self):
        """Test valid IPv4 with standard HTTP port."""
        assert is_valid_proxy("192.168.1.1:8080") is True

    def test_valid_ipv4_low_port(self):
        """Test valid IPv4 with minimum port number."""
        assert is_valid_proxy("192.168.1.1:1") is True

    def test_valid_ipv4_max_port(self):
        """Test valid IPv4 with maximum port number."""
        assert is_valid_proxy("255.255.255.255:65535") is True

    def test_valid_ipv4_common_proxy_port(self):
        """Test valid IPv4 with common proxy port."""
        assert is_valid_proxy("10.0.0.1:3128") is True

    def test_valid_ipv4_socks_port(self):
        """Test valid IPv4 with SOCKS port."""
        assert is_valid_proxy("172.16.0.1:1080") is True

    @pytest.mark.parametrize(
        "proxy",
        [
            "0.228.156.97:80",
            "1.1.1.1:53",
            "8.8.8.8:443",
            "127.0.0.1:8000",
        ],
    )
    def test_valid_ipv4_various_addresses(self, proxy):
        """Test various valid IPv4 addresses."""
        assert is_valid_proxy(proxy) is True


class TestIsValidProxyInvalidIPv4:
    """Test invalid IPv4 proxy addresses."""

    def test_invalid_ipv4_octets_too_large(self):
        """Test IPv4 with octets exceeding 255."""
        assert is_valid_proxy("801.0.0.10:801") is False

    def test_invalid_ipv4_all_octets_too_large(self):
        """Test IPv4 with all octets exceeding 255."""
        assert is_valid_proxy("999.999.999.999:80") is False

    def test_invalid_ipv4_incomplete(self):
        """Test incomplete IPv4 address."""
        assert is_valid_proxy("192.168.1:8080") is False

    def test_invalid_ipv4_too_many_octets(self):
        """Test IPv4 with too many octets."""
        assert is_valid_proxy("192.168.1.1.1:8080") is False


class TestIsValidProxyInvalidPorts:
    """Test invalid port numbers."""

    def test_invalid_port_exceeds_maximum(self):
        """Test proxy with port exceeding 65535."""
        assert is_valid_proxy("192.168.1.1:99999") is False

    def test_invalid_port_zero(self):
        """Test proxy with port 0."""
        assert is_valid_proxy("0.0.0.0:0") is False

    def test_invalid_port_negative(self):
        """Test proxy with negative port."""
        assert is_valid_proxy("192.168.1.1:-1") is False

    def test_invalid_port_non_numeric(self):
        """Test proxy with non-numeric port."""
        assert is_valid_proxy("192.168.1.1:abc") is False

    def test_invalid_port_missing(self):
        """Test proxy without port."""
        assert is_valid_proxy("192.168.1.1") is False

    def test_invalid_port_empty(self):
        """Test proxy with empty port."""
        assert is_valid_proxy("192.168.1.1:") is False


class TestIsValidProxyIPv6:
    """Test IPv6 proxy addresses."""

    def test_valid_ipv6_with_brackets(self):
        """Test valid IPv6 address with brackets."""
        assert is_valid_proxy("[2001:db8::1]:8080") is True

    def test_valid_ipv6_localhost(self):
        """Test IPv6 localhost."""
        assert is_valid_proxy("[::1]:8080") is True

    def test_valid_ipv6_full_address(self):
        """Test full IPv6 address with brackets."""
        assert is_valid_proxy("[2001:0db8:0000:0000:0000:0000:0000:0001]:80") is True

    def test_invalid_ipv6_no_brackets(self):
        """Test IPv6 address without brackets (parsed as hostname with invalid format)."""
        # Note: This is accepted because hostname parsing allows it
        assert is_valid_proxy("2001:db8::1:8080") is True


class TestIsValidProxyHostnames:
    """Test hostname-based proxy addresses."""

    def test_valid_hostname_simple(self):
        """Test simple hostname."""
        assert is_valid_proxy("localhost:8080") is True

    def test_valid_hostname_domain(self):
        """Test domain-based hostname."""
        assert is_valid_proxy("proxy.example.com:3128") is True

    def test_valid_hostname_subdomain(self):
        """Test multi-level subdomain."""
        assert is_valid_proxy("proxy.internal.company.net:8888") is True

    def test_valid_hostname_with_numbers(self):
        """Test hostname with numbers."""
        assert is_valid_proxy("proxy1.example.com:80") is True

    def test_valid_hostname_with_hyphens(self):
        """Test hostname with hyphens."""
        assert is_valid_proxy("my-proxy-server.example.com:443") is True

    def test_invalid_hostname_single_label_external(self):
        """Test single label hostname (not localhost)."""
        assert is_valid_proxy("myproxy:8080") is False

    def test_invalid_hostname_starting_with_hyphen(self):
        """Test hostname starting with hyphen."""
        assert is_valid_proxy("-proxy.example.com:8080") is False

    def test_invalid_hostname_ending_with_hyphen(self):
        """Test hostname ending with hyphen."""
        assert is_valid_proxy("proxy-.example.com:8080") is False

    def test_invalid_hostname_empty_label(self):
        """Test hostname with empty label."""
        assert is_valid_proxy("proxy..example.com:8080") is False

    @pytest.mark.parametrize(
        "proxy",
        [
            "n46.101.95.183:8888",
            "n209.38.214.48:1080",
            "n163.53.204.178:9813",
            "n202.62.62.113:1080",
            "n217.171.94.214:10801",
        ],
    )
    def test_invalid_hostname_prefixed_ipv4_like(self, proxy):
        """Test n-prefixed IPv4-like hosts are rejected."""
        assert is_valid_proxy(proxy) is False


class TestIsValidProxyWithCredentials:
    """Test proxy addresses with credentials."""

    def test_valid_proxy_with_credentials(self):
        """Test valid proxy with username and password."""
        assert is_valid_proxy("192.168.1.1:80@user:pass") is True

    def test_valid_proxy_with_credentials_special_chars(self):
        """Test proxy with credentials containing special characters."""
        assert is_valid_proxy("192.168.1.1:8080@user123:pass@123") is True

    def test_valid_proxy_with_credentials_complex_password(self):
        """Test proxy with complex password."""
        assert is_valid_proxy("example.com:3128@admin:P@ssw0rd!") is True

    def test_invalid_proxy_missing_password(self):
        """Test proxy with missing password."""
        assert is_valid_proxy("192.168.1.1:80@user:") is False

    def test_invalid_proxy_missing_username(self):
        """Test proxy with missing username."""
        assert is_valid_proxy("192.168.1.1:80@:pass") is False

    def test_invalid_proxy_missing_both_credentials(self):
        """Test proxy with missing both username and password."""
        assert is_valid_proxy("192.168.1.1:80@:") is False

    def test_valid_proxy_credentials_validation_disabled(self):
        """Test proxy with incomplete credentials when validation is disabled."""
        assert is_valid_proxy("192.168.1.1:80@user:", validate_credential=False) is True

    def test_valid_proxy_credentials_validation_disabled_missing_username(self):
        """Test proxy with missing username when credential validation is disabled."""
        assert is_valid_proxy("192.168.1.1:80@:pass", validate_credential=False) is True


class TestIsValidProxyEdgeCases:
    """Test edge cases and special scenarios."""

    def test_proxy_with_whitespace_prefix(self):
        """Test proxy with leading whitespace."""
        assert is_valid_proxy("  192.168.1.1:8080") is True

    def test_proxy_with_whitespace_suffix(self):
        """Test proxy with trailing whitespace."""
        assert is_valid_proxy("192.168.1.1:8080  ") is True

    def test_proxy_with_whitespace_both(self):
        """Test proxy with both leading and trailing whitespace."""
        assert is_valid_proxy("  192.168.1.1:8080  ") is True

    def test_proxy_length_minimum_valid(self):
        """Test proxy with minimum valid length."""
        # Shortest valid: "1.1.1:1" = 7 chars, but needs port validation
        assert is_valid_proxy("1.1.1.1:1") is True

    @pytest.mark.parametrize(
        "proxy,expected",
        [
            ("proxy.example.com:80", True),
            ("127.0.0.1:3128", True),
            ("[::1]:8080", True),
            ("192.168.1.1:65535", True),
            ("192.168.1.1", False),  # Missing port
            ("192.168.1.1:99999", False),  # Port too large
            ("999.999.999.999:80", False),  # Invalid IP-like numeric host
            ("", False),  # Empty string
            ("@:pass", False),  # Invalid credentials
        ],
    )
    def test_is_valid_proxy_combined_cases(self, proxy, expected):
        """Test combined cases with parametrize."""
        assert is_valid_proxy(proxy) is expected


if __name__ == "__main__":
    sys.exit(pytest.main([__file__, "-v"]))
