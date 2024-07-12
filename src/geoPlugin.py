import json
import os
import tempfile
from datetime import datetime, timedelta
from typing import Any, Optional, Union

import maxminddb
import requests
from geoip2 import database
from requests.exceptions import RequestException

from src.func import get_message_exception, get_nuitka_file, get_relative_path
from src.func_certificate import output_pem


def get_with_proxy(url, proxy_type: Optional[str] = 'http', proxy_raw: Optional[str] = None, timeout=10, debug: Optional[bool] = False):
    """
    Perform a GET request using a proxy of the specified type.

    Parameters:
    - url (str): The URL to perform the GET request on.
    - proxy_type (str): The type of the proxy. Possible values: 'http', 'socks4', 'socks5', 'https'.
    - proxy_url (str): The URL of the proxy to use (e.g., 'http://username:password@proxy_ip:proxy_port').
    - timeout (int): Timeout for the request in seconds (default is 10).

    Returns:
    - response (requests.Response): The response object returned by requests.get().
    """
    proxies = None

    if proxy_raw:
        split = proxy_raw.split('@')
        proxy = split[0]
        auth = None
        if len(split) > 1:
            auth = split[1]
        if proxy_type == 'socks4':
            proxies = {
                'http': f'socks4://{proxy}',
                'https': f'socks4://{proxy}'
            }
        elif proxy_type == 'socks5':
            proxies = {
                'http': f'socks5://{proxy}',
                'https': f'socks5://{proxy}'
            }
        else:
            proxies = {
                'http': proxy,
                'https': proxy
            }

    try:
        if proxies:
            response = requests.get(url, proxies=proxies, timeout=timeout, verify=output_pem)
        else:
            response = requests.get(url, timeout=timeout, verify=output_pem)

        response.raise_for_status()
        return response

    except RequestException as e:
        if debug:
            print(f"geoPlugin Error: {e}")
    return None


class GeoIpResult:
    """
    Represents a result from a GeoIP lookup.
    """

    def __init__(self, city: str, country_name: str, country_code: str, latitude: float, longitude: float,
                 timezone: str, region_name: Union[str, int], region: str, region_code: str, lang: str):
        """
        Initialize a GeoIpResult object.

        Args:
            city (str): The name of the city.
            country_name (str): The name of the country.
            country_code (str): The ISO 3166-1 alpha-2 country code.
            latitude (float): The latitude coordinate.
            longitude (float): The longitude coordinate.
            timezone (str): The timezone.
            region_name (str): The name of the region (e.g., state, province).
            region (str): The code of the region.
            region_code (str): The code of the region.
            lang (str): The language.
        """
        self.city = city
        self.country_name = country_name
        self.country_code = country_code
        self.latitude = latitude
        self.longitude = longitude
        self.timezone = timezone
        self.region_name = region_name
        self.region = region
        self.region_code = region_code
        self.lang = lang

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

    def from_dict(self, **kwargs: Any) -> 'GeoIpResult':
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


def get_geo_ip(proxy: str):
    ip = proxy
    split = proxy.split(":")
    if split:
        ip = split[0]
    try:
        with maxminddb.open_database(get_nuitka_file('src/GeoLite2-City.mmdb')) as reader:
            data = reader.get(ip)
            return data
    except Exception as e:
        print("Error in geoIp:", e)


def get_geo_ip2(proxy: str, proxy_username: Optional[str] = None, proxy_password: Optional[str] = None):
    ip = proxy
    split = proxy.split(":")
    if split:
        ip = split[0]
    reader = None
    try:
        reader = database.Reader(get_nuitka_file('src/GeoLite2-City.mmdb'))
        response = reader.city(ip)
        city = response.city.name
        country_name = response.country.name
        country_code = response.country.iso_code
        latitude = response.location.latitude
        longitude = response.location.longitude
        timezone = response.location.time_zone
        region_name = response.subdivisions.most_specific.name
        region = response.subdivisions.most_specific.geoname_id
        region_code = response.subdivisions.most_specific.iso_code
        languages = response.country.names.keys()
        if languages:
            lang = list(languages)[0]
        else:
            lang = None
        if country_code:
            lang_ = get_locale_from_country_code(country_code)
            if lang_:
                lang = lang_
                # print(f'locale from country code {country_code} is {lang}')
        reader.close()
        # fetch region name when maxmind fail
        if not region_name or not city or not timezone or not region or not region_code:
            for protocol in ['http', 'socks5', 'socks4']:
                try:
                    url = 'https://ip-get-geolocation.com/api/json'
                    proxy_url = f'{proxy}@{proxy_username}:{proxy_password}'
                    response = get_with_proxy(url, protocol, proxy_url)
                    if response and response.ok:
                        new_data = response.json()
                        if 'status' in new_data and str(new_data['status']).lower() == 'success':
                            print(new_data)
                            if 'city' in new_data and new_data['city'] and not city:
                                city = new_data['city']
                            if 'timezone' in new_data and new_data['timezone'] and not timezone:
                                timezone = new_data['timezone']
                            if 'regionName' in new_data and new_data['regionName'] and not region_name:
                                region_name = new_data['regionName']
                            if 'region' in new_data and new_data['region'] and not region:
                                region = new_data['region']
                            if 'regionCode' in new_data and new_data['regionCode'] and not region_code:
                                region_code = new_data['regionCode']
                            break
                except Exception:
                    pass
        return GeoIpResult(city, country_name, country_code, latitude, longitude, timezone,
                           region_name, region, region_code, lang)
    except Exception as e:
        print("Error in geoIp2:", get_message_exception(e))
        if reader is not None:
            reader.close()


def fetch_and_save_data(url, filename):
    try:
        response = requests.get(url)
        response.raise_for_status()  # Raise an exception for 4xx/5xx status codes
        countries_data = response.json()

        # Save data to file
        with open(filename, 'w') as f:
            json.dump(countries_data, f)

        return countries_data

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data: {e}")
        return None


# Load the countries JSON data from the URL
url = "https://raw.githubusercontent.com/annexare/Countries/main/dist/countries.min.json"

countries_data_path = get_relative_path('tmp/countries_data.json')

# Check if file exists and if it's not expired
if os.path.exists(countries_data_path):
    # Get file modification time
    file_mod_time = datetime.fromtimestamp(os.path.getmtime(countries_data_path))
    # Compare file modification time with the current time
    if (datetime.now() - file_mod_time) < timedelta(days=1):
        # File is not expired, load data from file
        with open(countries_data_path, 'r') as f:
            countries_data = json.load(f)
    else:
        # File is expired, fetch new data and save to file
        countries_data_get = fetch_and_save_data(url, countries_data_path)
        if countries_data_get:
            countries_data = countries_data_get
else:
    # File doesn't exist, fetch new data and save to file
    countries_data = fetch_and_save_data(url, countries_data_path)


def get_locale_from_country_code(country_code: str):
    if country_code in countries_data:
        country_info = countries_data[country_code]
        languages = country_info['languages']
        language_code = languages[0]  # Take the first language as primary language code
        return f"{language_code}_{country_code.upper()}"
    else:
        return None  # or handle as needed
