import os
import json
import requests
import pickle


class GeoPlugin:
    def __init__(self):
        self.host = "http://www.geoplugin.net/php.gp?ip={IP}&base_currency={CURRENCY}&lang={LANG}"
        self.currency = "USD"
        self.lang = "en"
        self.ip = None
        self.city = None
        self.region = None
        self.regionCode = None
        self.regionName = None
        self.dmaCode = None
        self.countryCode = None
        self.countryName = None
        self.inEU = None
        self.euVATrate = False
        self.continentCode = None
        self.continentName = None
        self.latitude = None
        self.longitude = None
        self.locationAccuracyRadius = None
        self.timezone = None
        self.currencyCode = None
        self.currencySymbol = None
        self.currencyConverter = None
        self.cacheFile = None

    def from_geo_ip2_city_model(self, record):
        if record:
            self.city = record["city"]["name"]
            self.countryName = record["country"]["name"]
            self.countryCode = record["country"]["isoCode"]
            self.latitude = record["location"]["latitude"]
            self.longitude = record["location"]["longitude"]
            self.timezone = record["location"]["timeZone"]
            self.regionName = record["mostSpecificSubdivision"]["name"]
            self.region = record["mostSpecificSubdivision"]["geonameId"]
            self.regionCode = record["mostSpecificSubdivision"]["isoCode"]
            lang = list(record["country"]["names"].keys())
            if lang:
                self.lang = lang[0]

    def json_serialize(self):
        return {key: value for key, value in vars(self).items()}

    def locate(self, ip=None):
        if ip is None:
            ip = requests.get("https://api.ipify.org?format=json").json().get("ip")
        host = self.host.format(IP=ip, CURRENCY=self.currency, LANG=self.lang)
        self.ip = ip
        response = self.fetch(host)
        return self.load_response(response)

    def load_response(self, response: requests.Response):
        if response:
            try:
                data = response.json()
                if data.get(
                    "geoplugin_status"
                ) == 429 or "too many request" in data.get("geoplugin_message", ""):
                    if os.path.exists(self.cacheFile):
                        os.remove(self.cacheFile)
                else:
                    self.city = data.get("geoplugin_city")
                    self.region = data.get("geoplugin_region")
                    self.regionCode = data.get("geoplugin_regionCode")
                    self.regionName = data.get("geoplugin_regionName")
                    self.dmaCode = data.get("geoplugin_dmaCode")
                    self.countryCode = data.get("geoplugin_countryCode")
                    self.countryName = data.get("geoplugin_countryName")
                    self.inEU = data.get("geoplugin_inEU")
                    self.euVATrate = data.get("geoplugin_euVATrate")
                    self.continentCode = data.get("geoplugin_continentCode")
                    self.continentName = data.get("geoplugin_continentName")
                    self.latitude = data.get("geoplugin_latitude")
                    self.longitude = data.get("geoplugin_longitude")
                    self.locationAccuracyRadius = data.get(
                        "geoplugin_locationAccuracyRadius"
                    )
                    self.timezone = data.get("geoplugin_timezone")
                    self.currencyCode = data.get("geoplugin_currencyCode")
                    self.currencySymbol = data.get("geoplugin_currencySymbol")
                    self.currencySymbol_UTF8 = data.get("geoplugin_currencySymbol_UTF8")
                    self.currencyConverter = data.get("geoplugin_currencyConverter")
            except json.JSONDecodeError:
                try:
                    data = pickle.loads(response.content)
                    if data:
                        self.city = data.get("geoplugin_city")
                        self.region = data.get("geoplugin_region")
                        self.regionCode = data.get("geoplugin_regionCode")
                        self.regionName = data.get("geoplugin_regionName")
                        self.dmaCode = data.get("geoplugin_dmaCode")
                        self.countryCode = data.get("geoplugin_countryCode")
                        self.countryName = data.get("geoplugin_countryName")
                        self.inEU = data.get("geoplugin_inEU")
                        self.euVATrate = data.get("geoplugin_euVATrate")
                        self.continentCode = data.get("geoplugin_continentCode")
                        self.continentName = data.get("geoplugin_continentName")
                        self.latitude = data.get("geoplugin_latitude")
                        self.longitude = data.get("geoplugin_longitude")
                        self.locationAccuracyRadius = data.get(
                            "geoplugin_locationAccuracyRadius"
                        )
                        self.timezone = data.get("geoplugin_timezone")
                        self.currencyCode = data.get("geoplugin_currencyCode")
                        self.currencySymbol = data.get("geoplugin_currencySymbol")
                        self.currencyConverter = data.get("geoplugin_currencyConverter")
                except Exception:
                    pass
        return response

    def locate_recursive(self, ip):
        geo = self.locate(ip)
        decoded_data = geo.json() if geo else {}
        if (
            decoded_data
            and decoded_data.get("geoplugin_status") == 429
            and "too many request" in decoded_data.get("geoplugin_message", "")
        ):
            if os.path.exists(self.cacheFile):
                os.remove(self.cacheFile)
            # geo2 = GeoPlugin2()  # Assuming GeoPlugin2 is defined similarly
            # geoplugin = geo2.locate(ip)
            # self.lang = geoplugin.lang
            # self.latitude = geoplugin.latitude
            # self.longitude = geoplugin.longitude
            # self.timezone = geoplugin.timezone
            # self.city = geoplugin.city
            # self.countryName = geoplugin.countryName
            # self.countryCode = geoplugin.countryCode
            # self.regionName = geoplugin.regionName
            # self.region = geoplugin.region
            # self.regionCode = geoplugin.regionCode
        return self

    def fetch(self, host):
        cache_dir = os.getcwd() + "/.cache/"
        self.cacheFile = cache_dir + str(hash(host))

        if not os.path.exists(cache_dir):
            os.makedirs(cache_dir)

        if os.path.exists(self.cacheFile):
            with open(self.cacheFile, "rb") as cache_file:
                return pickle.load(cache_file)

        try:
            response = requests.get(
                host, headers={"User-Agent": "geoPlugin Python Class v1.1"}
            )
            http_status = response.status_code
            if http_status == 200 and response.content:
                with open(self.cacheFile, "wb") as cache_file:
                    pickle.dump(response, cache_file)
            elif os.path.exists(self.cacheFile):
                os.remove(self.cacheFile)
            return response
        except requests.RequestException as e:
            print(f"Error: {e}")
            return None

    def convert(self, amount, float=2, symbol=True):
        if (
            not isinstance(self.currencyConverter, (int, float))
            or self.currencyConverter == 0
        ):
            print("Notice: currencyConverter has no value.")
            return amount
        if not isinstance(amount, (int, float)):
            print("Warning: The amount passed to GeoPlugin::convert is not numeric.")
            return amount
        return (
            f"{self.currencySymbol}{round(amount * self.currencyConverter, float)}"
            if symbol
            else round(amount * self.currencyConverter, float)
        )

    def nearby(self, radius=10, limit=None):
        if not isinstance(self.latitude, (int, float)) or not isinstance(
            self.longitude, (int, float)
        ):
            print("Warning: Incorrect latitude or longitude values.")
            return []
        host = f"http://www.geoplugin.net/extras/nearby.gp?lat={self.latitude}&long={self.longitude}&radius={radius}"
        if isinstance(limit, int):
            host += f"&limit={limit}"
        response = self.fetch(host)
        return pickle.loads(response.content) if response else []

    def __str__(self) -> str:
        """
        Return a JSON string representation of the object.
        """
        return json.dumps(self.__dict__)

    def __repr__(self) -> str:
        """
        Return a JSON string representation of the object.
        """
        return json.dumps(self.__dict__)
