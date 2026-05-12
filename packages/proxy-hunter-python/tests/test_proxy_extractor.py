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
    input_str = "147.75.68.200:10098@ProxyUser:ProxyPass"
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    # Expect a proxy with credentials
    assert any(p.proxy == "147.75.68.200:10098" and p.has_credentials() for p in result)
    p = next(p for p in result if p.proxy == "147.75.68.200:10098")
    assert p.username == "ProxyUser"
    assert p.password == "ProxyPass"


def test_extract_proxies_with_auth_prefix():
    input_str = "ProxyUser:ProxyPass@147.75.68.200:10098"
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    # Expect a proxy with credentials for prefixed auth as well
    assert any(p.proxy == "147.75.68.200:10098" and p.has_credentials() for p in result)
    p = next(p for p in result if p.proxy == "147.75.68.200:10098")
    assert p.username == "ProxyUser"
    assert p.password == "ProxyPass"


def test_extract_proxies_json_ip_port():
    input_str = '{"ip": "147.75.68.200","port":"10098"}'
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    assert any(p.proxy == "147.75.68.200:10098" for p in result)
    # No credentials expected for plain ip:port in JSON
    assert all(not p.has_credentials() for p in result)


def test_extract_proxies_json_ip_port_auth():
    input_str = (
        '{"ip": "147.75.68.200","port":"10098", "user":"ProxyUser", "pass":"ProxyPass"}'
    )
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    # Expect proxy and credentials extracted from JSON user/pass fields
    assert any(p.proxy == "147.75.68.200:10098" and p.has_credentials() for p in result)
    p = next(p for p in result if p.proxy == "147.75.68.200:10098")
    assert p.username == "ProxyUser"
    assert p.password == "ProxyPass"


def test_extract_proxies_json_format2():
    input_str = '{"proxy": "147.75.68.200:10098"}'
    result = extract_proxies(input_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    assert any(p.proxy == "147.75.68.200:10098" for p in result)
    # No credentials expected for plain ip:port in JSON
    assert all(not p.has_credentials() for p in result)


def test_extract_proxies_domain_with_auth():
    proxy_str = f"""another long string proxy_user:proxy_password@dc.oxylabs.io:8000 another long string
wgbfrmqf:lynb55lcsui6@173.0.9.209:5792
custom_proxy: http://dimaslanjaka_JD93N:myProxyCredentials=008@dc.oxylabs.io:8000
"""
    result = extract_proxies(proxy_str)
    assert isinstance(
        result, (list, tuple)
    ), "extract_proxies should return a list or tuple"
    # Proxy count check
    assert len(result) == 3, f"Expected 3 proxies, got {len(result)}"
    # Expect a proxy with credentials for domain with auth
    assert any(p.has_credentials() for p in result)


def test_extract_proxies_ipv6_plain():
    input_str = "[2001:db8::1]:8080"
    result = extract_proxies(input_str)
    assert isinstance(result, (list, tuple))
    assert any(p.proxy == "[2001:db8::1]:8080" for p in result)
    assert all(not p.has_credentials() for p in result)


def test_extract_proxies_ipv6_with_auth_prefix():
    input_str = "user:pass@[2001:db8::1]:8080"
    result = extract_proxies(input_str)
    assert isinstance(result, (list, tuple))
    assert any(p.proxy == "[2001:db8::1]:8080" and p.has_credentials() for p in result)
    p = next(p for p in result if p.proxy == "[2001:db8::1]:8080")
    assert p.username == "user"
    assert p.password == "pass"


def test_extract_proxies_ipv6_json():
    input_str = '{"ip": "2001:db8::1","port":"8080"}'
    result = extract_proxies(input_str)
    assert isinstance(result, (list, tuple))
    assert any(
        p.proxy == "2001:db8::1:8080" or p.proxy == "[2001:db8::1]:8080" for p in result
    )


def test_should_not_extract_invalid_proxies():
    input_str = """
    invalidproxy:1234, user:pass@invalid, [2001:db8::1],
    ms-text-size-adjust:100
    -webkit-text-size-adjust:100
    B0C7E:69834
    B0.0s.lkd.0j:69834
    """
    result = extract_proxies(input_str)
    assert isinstance(result, (list, tuple))
    assert len(result) == 0, f"Expected 0 valid proxies, got {len(result)}"


def test_extract_proxies_mixed_content():
    input_str = """
    CX n217.171.94.214:10801. Skipping...
    XSDn209.38.214.48:1080
    'n209.38.214.48'. lorem ipsum 1.1.1.1:80n
    WEn209.38.214.48:1080. Skipping...
    XSDn217.12.209.4:1080
    'n217.12.209.4'. lorem ipsum 1.1.1.1:80n
    Gn217.12.209.4:1080. Skipping...

    below is 5 IPV6 proxies sample:
    [2001:0db8:85a3:0000:0000:8a2e:0370:7334]:8080
    [2607:f8b0:4005:080a::200e]:3128
    [2a03:2880:f003:c07:face:b00c::2]:1080
    [2404:6800:4003:c02::64]:8000
    [2001:4860:4860::8888]:1080
    """
    result = extract_proxies(input_str)
    assert isinstance(result, (list, tuple))
    assert len(result) == 9, f"Expected 9 valid proxies, got {len(result)}"
    assert any(p.proxy == "217.171.94.214:10801" for p in result)
    assert any(p.proxy == "209.38.214.48:1080" for p in result)
    assert any(p.proxy == "217.12.209.4:1080" for p in result)
    assert any(p.proxy == "1.1.1.1:80" for p in result)


@pytest.mark.parametrize(
    "proxy, expected",
    [
        ("44.226.21.44:0796", "44.226.21.44:796"),
        ("044.026.021.044:0796", "44.26.21.44:796"),
        ("177.26.112.65:5678:", "177.26.112.65:5678"),
        ("103.250.166.04:6667:", "103.250.166.4:6667"),
        ("http://174.138.165.126:33508", "174.138.165.126:33508"),
        ("n177.26.112.65:5678:", "177.26.112.65:5678"),
    ],
)
def test_extract_single_proxy_valid(proxy, expected):
    normalized = extract_proxies(proxy)[0].proxy
    assert normalized == expected


@pytest.mark.parametrize(
    "proxy, reason",
    [
        ("177.26.112.65:", "missing port"),
        ("999.09.9.9:1029", "Out of range IP octet"),
    ],
)
def test_extract_single_proxy_invalid(proxy, reason):
    result = extract_proxies(proxy)
    assert not result, f"Expected invalid proxy for case: {reason}"


if __name__ == "__main__":
    sys.exit(pytest.main([__file__]))
