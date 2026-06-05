from typing import Any, Dict, Optional, Union

import certifi
import requests


class ProxyCheckResult:
    def __init__(
        self,
        result: Optional[bool],
        latency: Union[float, int],
        error: Optional[str],
        status: Optional[int],
        private: bool,
        response: Optional[requests.Response] = None,
        proxy: Optional[str] = None,
        type: Optional[str] = None,
        url: Optional[str] = None,
        https: Optional[bool] = None,
        additional: Optional[Dict[str, Any]] = None,
    ):
        self.result = result
        self.latency = latency
        self.error = error
        self.status = status
        self.private = private
        self.certificate = certifi.where()
        self.response = response
        self.proxy = proxy
        self.type = type
        self.https = https
        self.url = url
        self.additional = additional

    def __str__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"

    def __repr__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"
