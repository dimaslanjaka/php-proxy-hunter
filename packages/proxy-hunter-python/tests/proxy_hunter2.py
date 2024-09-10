from proxy_hunter.proxyhunter2 import proxy_hunter2, log


def callback(proxy, is_port_open, is_proxy_active):
    log(
        f"{proxy}: {'port open' if is_port_open else 'port closed'}, {'proxy active' if is_proxy_active else 'proxy dead'}",
        end="\r" if not is_port_open else "\n",
    )


if __name__ == "__main__":
    proxy = "156.34.105.58:5678"
    proxy_hunter2(proxy, callback)
