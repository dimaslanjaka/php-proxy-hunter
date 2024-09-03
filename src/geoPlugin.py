import json
import os
import re
import sqlite3
import sys
import traceback
from datetime import datetime, timedelta
from typing import Any, Dict, List, Optional, Union
from urllib.parse import urlparse

import pycountry
import pytz
import requests
from geoip2 import database
from geopy.exc import GeocoderTimedOut
from geopy.geocoders import Nominatim
from proxy_hunter import decompress_requests_response
from timezonefinder import TimezoneFinder

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from src.func import get_nuitka_file, get_relative_path
from proxy_hunter.utils.file import write_file
from src.geoPluginClass import GeoPlugin
from src.requests_cache import delete_cached_response, get_with_proxy


class GeoIpResult:
    """
    Represents a result from a GeoIP lookup.
    """

    def __init__(
        self,
        city: Optional[str] = None,
        country_name: Optional[str] = None,
        country_code: Optional[str] = None,
        latitude: Optional[float] = None,
        longitude: Optional[float] = None,
        timezone: Optional[str] = None,
        region_name: Optional[Union[str, int]] = None,
        region: Optional[str] = None,
        region_code: Optional[str] = None,
        lang: Optional[str] = None,
        **kwargs,  # Allows additional dynamic attributes
    ):
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
            **kwargs: Additional dynamic attributes.
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
        self.__dict__.update(kwargs)  # Update with additional attributes

    def __str__(self) -> str:
        """
        Return a JSON string representation of the object.
        """
        return json.dumps(self.to_dict())

    def __repr__(self) -> str:
        """
        Return a JSON string representation of the object.
        """
        return json.dumps(self.to_dict())

    @staticmethod
    def from_dict(data: Dict[str, Any]) -> "GeoIpResult":
        """
        Create a GeoIpResult instance from a dictionary.

        Args:
            data (Dict[str, Any]): A dictionary containing attribute names and values.

        Returns:
            GeoIpResult: An instance of GeoIpResult.
        """
        return GeoIpResult(**data)

    def update_from_dict(self, data: Dict[str, Any]) -> None:
        """
        Update the current instance with values from a dictionary.

        Args:
            data (Dict[str, Any]): A dictionary containing attribute names and values.
        """
        self.__dict__.update(data)

    def to_dict(self) -> Dict[str, Any]:
        """
        Transform current result into dictionary.

        Returns:
            Dict[str, Any]: Dictionary representation of the GeoIpResult instance.
        """
        return dict(self.__dict__)

    def to_json(self) -> str:
        """
        Convert GeoIpResult instance to JSON string.

        Returns:
            str: JSON representation of the GeoIpResult instance.
        """
        return json.dumps(self.to_dict())


def get_geo_ip2(
    proxy: str,
    proxy_username: Optional[str] = None,
    proxy_password: Optional[str] = None,
) -> Union[GeoIpResult, None]:
    ip = proxy.split(":")[0]
    (
        city,
        country_name,
        country_code,
        latitude,
        longitude,
        timezone,
        region_name,
        region,
        region_code,
        lang,
    ) = [None] * 10

    try:
        db_file = get_relative_path("src/GeoLite2-City.mmdb")
        if not os.path.exists(db_file):
            db_file = get_nuitka_file("src/GeoLite2-City.mmdb")
        with database.Reader(db_file) as reader:
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
            lang = list(languages)[0] if languages else None

            if not all(
                [lang, timezone, longitude, latitude, country_name, country_code]
            ):
                print(f"geo={proxy}", "fetching ip-get-geolocation.com")
                for protocol in ["http", "socks5", "socks4"]:
                    try:
                        # limit 50 request each day (USE YOUR OWN KEY)
                        # url = f"https://ip-get-geolocation.com/api/json/{ip}?key=8e95a158295f2664e05859cce73f8507"
                        url = "https://ip-get-geolocation.com/api/json"
                        proxy_url = (
                            f"{proxy}@{proxy_username}:{proxy_password}"
                            if proxy_username and proxy_password
                            else proxy
                        )
                        igg_cache_file = get_relative_path(
                            f"tmp/requests_cache/{ip}-ip-get-geolocation.json"
                        )
                        response = get_with_proxy(
                            url, protocol, proxy_url, cache_file_path=igg_cache_file
                        )
                        if response and response.ok:
                            new_data = response.json()
                            if new_data.get("status", "").lower() == "success":
                                city = new_data.get("city", city)
                                timezone = new_data.get("timezone", timezone)
                                region_name = new_data.get("regionName", region_name)
                                region = new_data.get("region", region)
                                region_code = new_data.get("regionCode", region_code)
                                country_name = new_data.get("country", country_name)
                                country = new_data.get("country", country)
                                latitude = new_data.get("lat", latitude)
                                longitude = new_data.get("lon", longitude)
                                break
                            else:
                                print(
                                    f"ip-get-geolocation.com failed {json.dumps(new_data, indent=2)}"
                                )
                                # delete cached response
                                delete_cached_response(url)
                        else:
                            print(f"ip-get-geolocation.com failed. No response.")
                    except Exception as e:
                        print(f"ip-get-geolocation.com Error: {e}")

            if not all(
                [lang, timezone, longitude, latitude, country_name, country_code]
            ):
                print(f"geo={proxy}", "fetching www.geoplugin.net")
                conn = sqlite3.connect(get_relative_path("src/database.sqlite"))
                cursor = conn.cursor()
                cursor.execute(
                    "SELECT * from proxies WHERE status = 'active' ORDER BY RANDOM()"
                )
                rows = cursor.fetchall()
                url = (
                    f"http://www.geoplugin.net/php.gp?ip={ip}&base_currency=USD&lang=en"
                )
                for row in rows:
                    row_proxy = row[1]
                    row_types = str(row[4]).split("-") if row[4] else []
                    for proxy_type in row_types:
                        try:
                            response = get_with_proxy(url, proxy_type, row_proxy)
                            if response and response.ok:
                                gp = GeoPlugin()
                                gp.load_response(response)
                                country_name = gp.countryName or country_name
                                latitude = gp.latitude or latitude
                                longitude = gp.longitude or longitude
                                country_code = gp.countryCode or country_code
                                timezone = gp.timezone or timezone
                                city = gp.city or city
                                region = gp.region or region
                                region_code = gp.regionCode or region_code
                                region_name = gp.regionName or region_name
                                lang = gp.lang or lang
                                break
                        except Exception as e:
                            print(
                                f"www.geoplugin.net error {proxy_type}://{row_proxy}: {e}"
                            )
                            traceback.print_exc()
                    if latitude and longitude:
                        break

            if not all([country_name, country_code]):
                print(f"geo={proxy}", "fetching cloudflare.com")
                url = "https://cloudflare.com/cdn-cgi/trace"
                try:
                    response = get_with_proxy(url, proxy_type, row_proxy, no_cache=True)
                    if response and response.ok:
                        text = decompress_requests_response(response)

                        # Split the text into lines using regex for different line endings
                        lines = re.split(r"\r?\n", text.strip())

                        # Create dictionary from lines
                        data_dict = dict(
                            line.split("=", 1) for line in lines if "=" in line
                        )

                        # Strip whitespace from keys and values
                        data_dict = {k.strip(): v.strip() for k, v in data_dict.items()}

                        country_code = data_dict["loc"]
                        if country_code:
                            test = get_country_name(country_code)
                            if test:
                                country_name = test
                                country = test
                            if not timezone:
                                tzc = get_timezones_by_country_code(country_code)
                                if tzc:
                                    timezone = tzc[0]
                except Exception:
                    pass

            if country_code:
                lang = get_locale_from_country_code(country_code)
                if not lang:
                    print(f"geo={proxy}", "fetching locale")
                    lang = get_locale_from_country_code(country_code)

            return GeoIpResult(
                city,
                country_name,
                country_code,
                latitude,
                longitude,
                timezone,
                region_name,
                region,
                region_code,
                lang,
            )

    except Exception as e:
        print(f"Error in geoIp2: {e}")
        return None


def get_country_name(country_code: str) -> Optional[str]:
    """
    Retrieve the country name from a given country code.

    Args:
        country_code (str): The 2-letter or 3-letter country code.

    Returns:
        Optional[str]: The name of the country if the code is valid, otherwise None.
    """
    try:
        country = pycountry.countries.get(alpha_2=country_code)
        if not country:
            country = pycountry.countries.get(alpha_3=country_code)
        return country.name if country else None
    except LookupError:
        return None


def fetch_and_save_data(url, filename):
    try:
        response = requests.get(url)
        response.raise_for_status()  # Raise an exception for 4xx/5xx status codes
        countries_data = response.json()

        # Save data to file
        write_file(filename, json.dumps(countries_data))

        return countries_data

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data: {e}")
        return None


# Load the countries JSON data from the URL
url = (
    "https://raw.githubusercontent.com/annexare/Countries/main/dist/countries.min.json"
)

countries_data_path = get_relative_path("tmp/countries_data.json")

# Check if file exists and if it's not expired
if os.path.exists(countries_data_path):
    # Get file modification time
    file_mod_time = datetime.fromtimestamp(os.path.getmtime(countries_data_path))
    # Compare file modification time with the current time
    if (datetime.now() - file_mod_time) < timedelta(days=1):
        # File is not expired, load data from file
        with open(countries_data_path, "r") as f:
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
        languages = country_info["languages"]
        language_code = languages[0]  # Take the first language as primary language code
        return f"{language_code}_{country_code.upper()}"
    else:
        return None  # or handle as needed


def download_databases(folder: str):
    """
    download geolite2 .mmdb databases
    """
    if not folder:
        return
    # Ensure the folder exists
    os.makedirs(folder, exist_ok=True)

    urls = [
        "https://git.io/GeoLite2-ASN.mmdb",
        "https://git.io/GeoLite2-City.mmdb",
        "https://git.io/GeoLite2-Country.mmdb",
        "https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-City.mmdb",
        "https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-ASN.mmdb",
        "https://github.com/P3TERX/GeoLite.mmdb/raw/download/GeoLite2-Country.mmdb",
    ]

    for url in urls:
        # Extract filename from the URL
        filename = os.path.basename(urlparse(url).path)
        file_path = os.path.join(folder, filename)

        # Download the file
        response = requests.get(url, stream=True)
        if response.status_code == 200:
            # Check the size of the downloaded file
            downloaded_size = int(response.headers.get("content-length", 0))

            # Check if the file already exists and get its size
            if os.path.exists(file_path):
                existing_size = os.path.getsize(file_path)
            else:
                existing_size = 0

            # Overwrite if the downloaded file is larger
            if downloaded_size > existing_size:
                with open(file_path, "wb") as file:
                    file.write(response.content)
                print(f"Downloaded and saved {file_path}")
            else:
                print(f"Skipped {file_path}: Existing file is larger or equal in size.")
        else:
            print(f"Failed to download {url}")


def get_timezones_by_country_code(country_code: str) -> Optional[List[str]]:
    """
    Get a list of timezones associated with a given country code.

    Args:
        country_code (str): The ISO 3166-1 alpha-2 country code (e.g., 'US').

    Returns:
        Optional[List[str]]: A list of timezone strings if found, otherwise None.
    """
    # Get the country name from the country code
    country = pycountry.countries.get(alpha_2=country_code)
    if not country:
        return None

    # Get all timezones
    timezones = pytz.all_timezones

    # Filter timezones based on the country
    # Note: This method assumes timezones start with the country name, which may not be accurate.
    country_timezones = [tz for tz in timezones if tz.startswith(f"{country.name}/")]

    test = country_timezones if country_timezones else None
    if not test:
        timezones = pytz.country_timezones.get(country_code.upper())
        test = timezones if timezones else None
    if test:
        return test
    else:
        geolocator = Nominatim(user_agent="timezone_lookup")
        try:
            location = geolocator.geocode(f"country code {country_code}")
            if location:
                return [
                    location.raw.get("address", {}).get(
                        "timezone", "No timezone information"
                    )
                ]
            return None
        except GeocoderTimedOut:
            return None


def get_timezones_by_lat_lon(latitude: float, longitude: float) -> Optional[List[str]]:
    """
    Get a list of timezones based on latitude and longitude.

    Args:
        latitude (float): The latitude coordinate.
        longitude (float): The longitude coordinate.

    Returns:
        Optional[List[str]]: A list of timezone strings if found, otherwise None.
    """
    tf = TimezoneFinder()
    timezone = tf.timezone_at(lat=latitude, lng=longitude)
    return [timezone] if timezone else None


if __name__ == "__main__":
    download_databases("src")
    geo = get_geo_ip2("184.185.2.12:4145")
    if geo.country_code:
        tzc = get_timezones_by_country_code(geo.country_code)
        print(f"Timezone from country code:\t\t{tzc}")
    if geo.latitude and geo.longitude:
        tzl = get_timezones_by_lat_lon(geo.latitude, geo.longitude)
        print(f"Timezone from latitude and longitude:\t{tzc}")
