import json
from typing import Any, Dict, List, Optional


class Proxy:
    """
    Proxy table data class
    """

    def __init__(
        self,
        proxy: str,
        id: Optional[int] = None,
        latency: Optional[str] = None,
        type: Optional[str] = None,  # Renamed from `type`
        region: Optional[str] = None,
        city: Optional[str] = None,
        country: Optional[str] = None,
        last_check: Optional[str] = None,
        anonymity: Optional[str] = None,
        status: Optional[str] = None,
        timezone: Optional[str] = None,
        longitude: Optional[str] = None,
        private: Optional[str] = None,
        latitude: Optional[str] = None,
        lang: Optional[str] = None,
        useragent: Optional[str] = None,
        webgl_vendor: Optional[str] = None,
        webgl_renderer: Optional[str] = None,
        browser_vendor: Optional[str] = None,
        username: Optional[str] = None,
        password: Optional[str] = None,
        https: Optional[str] = None,  # Optional HTTPS protocol
    ) -> None:
        """
        Proxy constructor.

        :param proxy: Proxy IP address or hostname.
        :param id: Proxy ID.
        :param latency: Proxy latency.
        :param type: Proxy type.
        :param region: Proxy region.
        :param city: Proxy city.
        :param country: Proxy country.
        :param last_check: Last check timestamp.
        :param anonymity: Proxy anonymity.
        :param status: Proxy status.
        :param timezone: Proxy timezone.
        :param longitude: Proxy longitude.
        :param private: Whether the proxy is private.
        :param latitude: Proxy latitude.
        :param lang: Language.
        :param useragent: User agent.
        :param webgl_vendor: WebGL vendor.
        :param webgl_renderer: WebGL renderer.
        :param browser_vendor: Browser vendor.
        :param username: Username for proxy authentication.
        :param password: Password for proxy authentication.
        :param https: HTTPS protocol support.
        """
        self.id = id
        self.proxy = proxy
        self.latency = latency
        self.type = type
        self.region = region
        self.city = city
        self.country = country
        self.last_check = last_check
        self.anonymity = anonymity
        self.status = status
        self.timezone = timezone
        self.longitude = longitude
        self.private = private
        self.latitude = latitude
        self.lang = lang
        self.useragent = useragent
        self.webgl_vendor = webgl_vendor
        self.webgl_renderer = webgl_renderer
        self.browser_vendor = browser_vendor
        self.username = username
        self.password = password
        self.https = https

    def has_credentials(self):
        return self.username and self.password

    def __str__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"

    def __repr__(self):
        attributes = ", ".join(f"{key}: {value}" for key, value in vars(self).items())
        return f"Proxy({attributes})"

    def format(self) -> str:
        """
        Format the Person instance into a connection string.

        Returns:
        str: The formatted connection string.
        """
        raw = f"{self.proxy}"
        if self.username is not None and self.password is not None:
            if self.username.strip() and self.password.strip():
                raw = f"{self.proxy}@{self.username}:{self.password}"
        return raw

    def from_dict(self, **kwargs: Any) -> "Proxy":
        """
        Proxy class constructor.

        Args:
            **kwargs: Keyword arguments representing proxy attributes.
        Example:
            print(Proxy("ip:port").from_dict(**dictionary))
        """
        for key, value in kwargs.items():
            setattr(self, key, value)
        return self

    def to_dict(self):
        """
        Transform current result into dict ProxyDB
        """
        properties = {}
        for attr, value in vars(self).items():
            properties[attr] = value
        return properties

    def to_json(self) -> str:
        """
        Convert Proxy instance to JSON string.

        Returns:
        str: JSON representation of the Proxy instance.
        """
        return json.dumps(self.to_dict())


def dict_to_proxy_list(dict_list: List[Dict[str, Any]]) -> List[Proxy]:
    """
    Convert a list of dictionaries to a list of Proxy objects.

    :param dict_list: List of dictionaries, each representing a proxy with its attributes.
    :return: List of Proxy objects instantiated from the provided dictionaries.
    """
    proxy_list = []
    for item in dict_list:
        proxy = Proxy(
            proxy=str(item.get("proxy")),
            id=item.get("id"),
            latency=item.get("latency"),
            type=item.get("type"),
            region=item.get("region"),
            city=item.get("city"),
            country=item.get("country"),
            last_check=item.get("last_check"),
            anonymity=item.get("anonymity"),
            status=item.get("status"),
            timezone=item.get("timezone"),
            longitude=item.get("longitude"),
            private=item.get("private"),
            latitude=item.get("latitude"),
            lang=item.get("lang"),
            useragent=item.get("useragent"),
            webgl_vendor=item.get("webgl_vendor"),
            webgl_renderer=item.get("webgl_renderer"),
            browser_vendor=item.get("browser_vendor"),
            username=item.get("username"),
            password=item.get("password"),
        )
        proxy_list.append(proxy)
    return proxy_list
