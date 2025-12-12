import sys
import pytest
from proxy_hunter import extract_proxies


def test_extract_proxies_ip_port_only():
    input_str = "8.8.8.8:8080"
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    assert any(p.proxy == "8.8.8.8:8080" for p in result)
    # No credentials expected for plain ip:port
    assert all(not p.has_credentials() for p in result)


def test_extract_proxies_with_auth_suffix():
    input_str = "1.2.3.4:8080@ProxyUser:ProxyPass"
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    # Expect a proxy with credentials
    assert any(p.proxy == "1.2.3.4:8080" and p.has_credentials() for p in result)
    p = next(p for p in result if p.proxy == "1.2.3.4:8080")
    assert p.username == "ProxyUser"
    assert p.password == "ProxyPass"


def test_extract_proxies_with_auth_prefix():
    input_str = "ProxyUser:ProxyPass@1.2.3.4:8080"
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    # Expect a proxy with credentials for prefixed auth as well
    assert any(p.proxy == "1.2.3.4:8080" and p.has_credentials() for p in result)
    p = next(p for p in result if p.proxy == "1.2.3.4:8080")
    assert p.username == "ProxyUser"
    assert p.password == "ProxyPass"


if __name__ == "__main__":
    sys.exit(pytest.main([__file__]))
