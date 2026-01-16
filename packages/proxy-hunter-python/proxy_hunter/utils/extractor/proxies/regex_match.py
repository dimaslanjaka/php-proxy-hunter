import re
from typing import List
from proxy_hunter.Proxy import Proxy

regex = r"(?P<user_pass_host>(?P<username1>[a-zA-Z0-9!$%&*()_+=.-]+):(?P<password1>[a-zA-Z0-9!$%&*()_+=.-]+)@(?P<host1>\d{1,3}(?:\.\d{1,3}){3}|[\w.-]+):(?P<port1>\d{2,5}))|(?P<host_user_pass>(?P<host2>\d{1,3}(?:\.\d{1,3}){3}|[\w.-]+):(?P<port2>\d{2,5})@(?P<username2>[a-zA-Z0-9!$%&*()_+=.-]+):(?P<password2>[a-zA-Z0-9!$%&*()_+=.-]+))|(?P<host_only>(?P<host3>\d{1,3}(?:\.\d{1,3}){3}|[\w.-]+):(?P<port3>\d{2,5}))"


def regex_match(test_str: str) -> List[Proxy]:
    matches = re.finditer(regex, test_str, re.MULTILINE)
    results = []

    for match in matches:
        if match.group("user_pass_host"):
            username = match.group("username1")
            password = match.group("password1")
            proxy = f"{match.group('host1')}:{match.group('port1')}"
        elif match.group("host_user_pass"):
            username = match.group("username2")
            password = match.group("password2")
            proxy = f"{match.group('host2')}:{match.group('port2')}"
        elif match.group("host_only"):
            username = password = None
            proxy = f"{match.group('host3')}:{match.group('port3')}"
        else:
            continue
        results.append(Proxy(proxy=proxy, username=username, password=password))
    return results


if __name__ == "__main__":
    test_str = (
        "another long string proxy_user:proxy_password@dc.oxylabs.io:8000 another long string\n"
        "wgbfrmqf:lynb55lcsui6@173.0.9.209:5792\n"
        "custom_proxy: http://dimaslanjaka_JD93N:myProxyCredentials=008@dc.oxylabs.io:8000\n"
        "aaa:bbb@173.0.9.209:5792\n"
        "173.0.9.209:5792@aaaa:bbbb"
    )

    result = regex_match(test_str)
    print(f"Found {len(result)} matches:")
    for r in result:
        print(f"  Proxy: {r.proxy}, Username: {r.username}, Password: {r.password}")
