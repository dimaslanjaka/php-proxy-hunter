from pathlib import Path
from typing import Any, Dict, List, Set, Tuple

import yaml

SUPPORTED_CLASH_TYPES = {"http", "socks5"}


def parse_proxy_endpoint(proxy: str) -> Tuple[str, int]:
    proxy = str(proxy or "").strip()

    if "://" in proxy:
        proxy = proxy.split("://", 1)[1]

    if "@" in proxy:
        proxy = proxy.rsplit("@", 1)[1]

    host, port = proxy.rsplit(":", 1)

    return host.strip("[]"), int(port)


def unique_proxy_name(
    proxy_type: str,
    server: str,
    port: int,
    used_names: Set[str],
) -> str:
    base_name = f"{proxy_type}-{server}-{port}"
    name = base_name
    counter = 2

    while name in used_names:
        name = f"{base_name}-{counter}"
        counter += 1

    used_names.add(name)
    return name


def generate_clash_verge_config_yaml(
    working_proxies: List[Dict[str, Any]],
    output_file: str,
) -> Dict[str, Any]:
    clash_proxy_nodes: List[Dict[str, Any]] = []
    clash_proxy_names: List[str] = []
    used_names: Set[str] = set()
    skipped: List[Dict[str, Any]] = []

    for row in working_proxies or []:
        if not isinstance(row, dict):
            continue

        status = str(row.get("status") or "").lower().strip()
        if status != "active":
            continue

        https = str(row.get("https") or "").lower().strip()
        if https != "true":
            continue

        proxy_type = str(row.get("type") or "").lower().strip()
        proxy_value = str(row.get("proxy") or "").strip()

        if not proxy_value:
            continue

        if proxy_type not in SUPPORTED_CLASH_TYPES:
            skipped.append(
                {
                    "proxy": proxy_value,
                    "type": proxy_type,
                    "reason": "unsupported_clash_type",
                }
            )
            continue

        try:
            server, port = parse_proxy_endpoint(proxy_value)
        except Exception as exc:
            skipped.append(
                {
                    "proxy": proxy_value,
                    "type": proxy_type,
                    "reason": f"parse_error: {exc}",
                }
            )
            continue

        name = unique_proxy_name(
            proxy_type=proxy_type,
            server=server,
            port=port,
            used_names=used_names,
        )

        node: Dict[str, Any] = {
            "name": name,
            "type": proxy_type,
            "server": server,
            "port": port,
        }

        if proxy_type == "socks5":
            node["udp"] = True

        clash_proxy_nodes.append(node)
        clash_proxy_names.append(name)

    if clash_proxy_names:
        proxy_groups = [
            {
                "name": "AUTO",
                "type": "fallback",
                "url": "https://www.gstatic.com/generate_204",
                "interval": 300,
                "proxies": clash_proxy_names,
            },
            {
                "name": "PROXY",
                "type": "select",
                "proxies": ["AUTO", *clash_proxy_names, "DIRECT"],
            },
        ]

        rules = [
            "MATCH,PROXY",
        ]
    else:
        proxy_groups = [
            {
                "name": "PROXY",
                "type": "select",
                "proxies": ["DIRECT"],
            },
        ]

        rules = [
            "MATCH,DIRECT",
        ]

    config = {
        "mixed-port": 7897,
        "allow-lan": False,
        "mode": "rule",
        "log-level": "info",
        "ipv6": False,
        "tun": {
            "enable": True,
            "stack": "mixed",
            "auto-route": True,
            "auto-detect-interface": True,
            "strict-route": True,
            "dns-hijack": [
                "any:53",
                "tcp://any:53",
            ],
        },
        "dns": {
            "enable": True,
            "enhanced-mode": "fake-ip",
            "nameserver": [
                "https://1.1.1.1/dns-query",
                "https://8.8.8.8/dns-query",
            ],
        },
        # IMPORTANT:
        # This must be list[dict], not list[str].
        "proxies": clash_proxy_nodes,
        "proxy-groups": proxy_groups,
        "rules": rules,
    }

    Path(output_file).parent.mkdir(parents=True, exist_ok=True)

    with open(output_file, "w", encoding="utf-8") as file:
        yaml.safe_dump(
            config,
            file,
            sort_keys=False,
            allow_unicode=True,
            default_flow_style=False,
        )

    return {
        "output_file": output_file,
        "proxy_count": len(clash_proxy_nodes),
        "skipped_count": len(skipped),
        "skipped": skipped,
    }
