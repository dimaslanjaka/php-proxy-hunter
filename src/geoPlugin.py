import os
import sqlite3
import sys
import traceback
from urllib.parse import urlparse


sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

import json
from datetime import datetime, timedelta
from typing import Any, Optional, Union

import requests
from geoip2 import database

from src.func import get_nuitka_file, get_relative_path, write_file
from src.requests_cache import get_with_proxy
from src.geoPluginClass import GeoPlugin


class GeoIpResult:
    """
    Represents a result from a GeoIP lookup.
    """

    def __init__(
        self,
        city: str,
        country_name: str,
        country_code: str,
        latitude: float,
        longitude: float,
        timezone: str,
        region_name: Union[str, int],
        region: str,
        region_code: str,
        lang: str,
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

    def from_dict(self, **kwargs: Any) -> "GeoIpResult":
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


def get_geo_ip2(
    proxy: str,
    proxy_username: Optional[str] = None,
    proxy_password: Optional[str] = None,
):
    ip = proxy
    split = proxy.split(":")
    if split:
        ip = split[0]
    reader = None
    try:
        reader = database.Reader(get_nuitka_file("src/GeoLite2-City.mmdb"))
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
            for protocol in ["http", "socks5", "socks4"]:
                try:
                    url = "https://ip-get-geolocation.com/api/json"
                    proxy_url = f"{proxy}@{proxy_username}:{proxy_password}"
                    response = get_with_proxy(url, protocol, proxy_url)
                    if response and response.ok:
                        new_data = response.json()
                        if (
                            "status" in new_data
                            and str(new_data["status"]).lower() == "success"
                        ):
                            print(new_data)
                            if "city" in new_data and new_data["city"] and not city:
                                city = new_data["city"]
                            if (
                                "timezone" in new_data
                                and new_data["timezone"]
                                and not timezone
                            ):
                                timezone = new_data["timezone"]
                            if (
                                "regionName" in new_data
                                and new_data["regionName"]
                                and not region_name
                            ):
                                region_name = new_data["regionName"]
                            if (
                                "region" in new_data
                                and new_data["region"]
                                and not region
                            ):
                                region = new_data["region"]
                            if (
                                "regionCode" in new_data
                                and new_data["regionCode"]
                                and not region_code
                            ):
                                region_code = new_data["regionCode"]
                            break
                except Exception:
                    pass

        if not latitude or not longitude:
            conn = sqlite3.connect(get_relative_path("src/database.sqlite"))
            cursor = conn.cursor()
            cursor.execute(
                "SELECT * from proxies WHERE status = 'active' ORDER BY RANDOM()"
            )
            rows = cursor.fetchall()
            # print(f"got {len(rows)} active proxies")
            url = f"http://www.geoplugin.net/php.gp?ip={ip}&base_currency=USD&lang=en"
            break_outer_loop = False
            for row in rows:
                row_proxy = row[1]
                row_type = str(row[4]).split("-") if row[4] else []
                for proxy_type in row_type:
                    try:
                        # print(f"fetch geolocation {proxy_type}://{row_proxy}")
                        fetch_url = get_with_proxy(url, proxy_type, row_proxy)
                        if fetch_url:
                            try:
                                gp = GeoPlugin()
                                gp.load_response(fetch_url)
                                country_name = gp.countryName
                                latitude = gp.latitude
                                longitude = gp.longitude
                                country_code = gp.countryCode
                                timezone = gp.timezone
                                city = gp.city
                                region = gp.region
                                region_code = gp.regionCode
                                region_name = gp.regionName
                                lang = gp.lang
                                break_outer_loop = True
                                break
                            except Exception:
                                pass
                    except Exception as e:
                        print(f"fail get {url} with {proxy_type}://{row_proxy} -> {e}")
                        traceback.print_exc()
                if break_outer_loop:
                    break

        if country_code:
            # get locale from country code
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
        if reader is not None:
            reader.close()


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


def download_databases(folder):
    """
    download geolite2 .mmdb databases
    """
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


if __name__ == "__main__":
    # download_databases("src")
    result = get_geo_ip2("104.17.75.127:80")
    print(result)
